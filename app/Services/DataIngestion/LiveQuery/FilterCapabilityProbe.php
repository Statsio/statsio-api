<?php

namespace App\Services\DataIngestion\LiveQuery;

use App\Domain\DataIngestion\Enums\ColumnTypeEnum;
use App\Services\DataIngestion\HttpProbeService;
use App\Services\DataIngestion\PaginatedApiFetcher;
use Illuminate\Support\Arr;

/**
 * Sonde une source API à la création d'une source "live" pour détecter
 * automatiquement quelles colonnes de la réponse fonctionnent comme filtres
 * de requête côté serveur (query_mapping), sans que l'utilisateur ait à
 * déclarer ce mapping à la main.
 *
 * Heuristique (pas de standard universel de filtrage REST) : pour chaque
 * colonne, on ré-interroge l'API avec `?nom_de_colonne=valeur_échantillon`
 * et on vérifie que le `count` retourné diminue par rapport à la baseline —
 * ce qui correspond à la convention "le nom de champ de la réponse est
 * directement un paramètre de requête" suivie par la plupart des API
 * open-data françaises (Hub'Eau, Sandre, data.gouv.fr...).
 *
 * Simplification assumée (v1) : ne détecte que le filtre exact (`eq`) et,
 * pour les colonnes date/datetime, un couple de bornes min/max. La détection
 * du support multi-valeurs (`in`, CSV vs paramètre répété) n'est pas
 * implémentée — un utilisateur qui en a besoin peut compléter `query_mapping`
 * manuellement dans le wizard (voir §2bis du plan).
 */
class FilterCapabilityProbe
{
    /** Budget total de requêtes de sondage, tous types confondus, pour borner le coût à la création. */
    private const MAX_PROBE_REQUESTS = 30;

    private const COUNT_PATH_CANDIDATES = ['count', 'total', 'total_count', 'totalElements', 'totalCount'];

    public function __construct(
        private readonly HttpProbeService $httpProbe,
        private readonly PaginatedApiFetcher $fetcher,
    ) {}

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $pagination
     * @param  array<string, mixed>  $baseBody  Corps de la réponse d'échantillon déjà récupérée (1 page)
     * @param  array<string, array{type: ColumnTypeEnum, nullable: bool, sample_values: array}>  $schema
     * @param  array<int, array<string, string|null>>  $sampleRows
     * @return array{count_path: ?string, max_page_size: ?int, filters: array<string, array>, sortable_columns: array, supports_distinct: bool, supports_joins: bool, supports_aggregate: bool}
     */
    public function detect(string $url, string $method, array $headers, ?string $dataPath, array $pagination, array $baseBody, array $schema, array $sampleRows): array
    {
        $countPath = $this->detectCountPath($baseBody);
        $baseCount = $countPath ? Arr::get($baseBody, $countPath) : null;
        $baseCount = is_numeric($baseCount) ? (float) $baseCount : null;

        $filters = [];
        $requestsUsed = 0;
        $maxColumns = (int) config('statsio.data_ingestion.live_query.probe_max_columns', 20);
        $timeout = (int) config('statsio.data_ingestion.live_query.probe_request_timeout_seconds', 10);

        if ($baseCount !== null) {
            foreach (array_slice(array_keys($schema), 0, $maxColumns) as $column) {
                if ($requestsUsed >= self::MAX_PROBE_REQUESTS) {
                    break;
                }

                $value = $this->representativeValue($sampleRows, $column);
                if ($value === null || $value === '' || $this->looksStructured($value)) {
                    continue;
                }

                $eqCount = $this->probeCount($url, $method, $headers, $dataPath, $pagination, [$column => $value], $countPath, $timeout);
                $requestsUsed++;

                if ($eqCount !== null && $eqCount < $baseCount) {
                    $filters[$column] = ['param' => $column, 'operators' => ['eq']];
                }

                if ($schema[$column]['type']->isTemporal() && $requestsUsed < self::MAX_PROBE_REQUESTS) {
                    [$range, $used] = $this->detectDateRange(
                        $url, $method, $headers, $dataPath, $pagination,
                        $column, $value, $countPath, $baseCount, $timeout,
                    );
                    $requestsUsed += $used;
                    if ($range) {
                        $filters[$column] = ['range' => $range, 'operators' => ['gte', 'lte']];
                    }
                }
            }
        }

        return [
            'count_path' => $countPath,
            'max_page_size' => null,
            'filters' => $filters,
            'sortable_columns' => [],
            'supports_distinct' => false,
            'supports_joins' => false,
            'supports_aggregate' => false,
        ];
    }

    private function detectCountPath(array $baseBody): ?string
    {
        foreach (self::COUNT_PATH_CANDIDATES as $candidate) {
            if (is_numeric(Arr::get($baseBody, $candidate))) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param  array<int, array<string, string|null>>  $sampleRows
     */
    private function representativeValue(array $sampleRows, string $column): ?string
    {
        foreach ($sampleRows as $row) {
            $value = $row[$column] ?? null;
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    private function looksStructured(string $value): bool
    {
        $trimmed = trim($value);

        return str_starts_with($trimmed, '[') || str_starts_with($trimmed, '{');
    }

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $pagination
     * @param  array<string, mixed>  $extraQuery
     */
    private function probeCount(string $url, string $method, array $headers, ?string $dataPath, array $pagination, array $extraQuery, ?string $countPath, int $timeout): ?float
    {
        if ($countPath === null) {
            return null;
        }

        try {
            $query = array_merge($this->fetcher->buildFirstPageQuery($pagination), $extraQuery);
            $page = $this->httpProbe->fetchPage($url, $method, $headers, $query, $timeout);
            $count = Arr::get($page['body'], $countPath);

            return is_numeric($count) ? (float) $count : null;
        } catch (\Throwable) {
            // Une colonne dont le nom n'est pas un paramètre de filtre valide provoque
            // souvent une erreur HTTP côté API — on l'ignore et on la considère non filtrable.
            return null;
        }
    }

    /**
     * Teste, dans l'ordre, les conventions de nommage de bornes de plage les plus
     * courantes pour une colonne date/datetime, en préférant la convention
     * Hub'Eau/Sandre (`date_min_{suffixe}`) en tête quand le nom de colonne
     * commence par `date_`, puisque c'est notre cible de référence.
     *
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $pagination
     * @return array{0: ?array{gte_param: string, lte_param: string}, 1: int} [plage détectée ou null, nombre de requêtes consommées]
     */
    private function detectDateRange(string $url, string $method, array $headers, ?string $dataPath, array $pagination, string $column, string $representativeValue, ?string $countPath, float $baseCount, int $timeout): array
    {
        $candidates = [];

        if (str_starts_with($column, 'date_')) {
            $suffix = substr($column, strlen('date_'));
            $candidates[] = ["date_min_{$suffix}", "date_max_{$suffix}"];
        }

        $candidates[] = ["{$column}_min", "{$column}_max"];
        $candidates[] = ["min_{$column}", "max_{$column}"];

        $used = 0;
        foreach ($candidates as [$gteParam, $lteParam]) {
            if ($used >= 3) {
                break; // ne pas dépenser plus que le nombre de conventions candidates
            }

            $count = $this->probeCount($url, $method, $headers, $dataPath, $pagination, [$gteParam => $representativeValue], $countPath, $timeout);
            $used++;

            if ($count !== null && $count < $baseCount) {
                return [['gte_param' => $gteParam, 'lte_param' => $lteParam], $used];
            }
        }

        return [null, $used];
    }
}
