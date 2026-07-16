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

    private const COUNT_PATH_CANDIDATES = ['count', 'total', 'total_count', 'totalElements', 'totalCount', 'totalItems', 'maxPage'];

    private const SEARCH_PARAM_CANDIDATES = ['search', 'q', 'query', 'keyword'];

    public function __construct(
        private readonly HttpProbeService $httpProbe,
        private readonly PaginatedApiFetcher $fetcher,
    ) {}

    /**
     * Conventions de nommage courantes pour un paramètre de filtre — utilisées
     * uniquement pour la PRIORISATION des colonnes à sonder (pas pour deviner
     * le nom du paramètre lui-même, qui reste toujours le nom de la colonne).
     */
    private const FILTER_NAME_PATTERNS = ['code_', 'id_', '_id', 'type_', 'categorie_'];

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $pagination
     * @param  array<string, mixed>  $baseBody  Corps de la réponse d'échantillon déjà récupérée (1 page)
     * @param  array<string, array{type: ColumnTypeEnum, nullable: bool, sample_values: array, semantic_role?: string}>  $schema
     * @param  array<int, array<string, string|null>>  $sampleRows
     * @param  ?int  $probeMaxColumns  Surcharge du nombre max de colonnes sondées (défaut config, réduit en contexte détection)
     * @param  ?int  $probeRequestTimeoutSeconds  Surcharge du timeout par requête (défaut config, réduit en contexte détection)
     * @param  ?float  $deadline  Timestamp absolu (microtime) au-delà duquel arrêter le sondage, résultat partiel retourné tel quel
     * @return array{count_path: ?string, max_page_size: ?int, filters: array<string, array>, sortable_columns: array, supports_distinct: bool, supports_joins: bool, supports_aggregate: bool, probe_truncated: bool, search_param?: ?string}
     */
    public function detect(string $url, string $method, array $headers, ?string $dataPath, array $pagination, array $baseBody, array $schema, array $sampleRows, ?int $probeMaxColumns = null, ?int $probeRequestTimeoutSeconds = null, ?float $deadline = null): array
    {
        $countPath = $this->detectCountPath($baseBody);
        $baseCount = $countPath ? Arr::get($baseBody, $countPath) : null;
        $baseCount = is_numeric($baseCount) ? (float) $baseCount : null;

        $searchParam = $this->detectSearchParam($url, $method, $headers, $dataPath, $sampleRows, $probeRequestTimeoutSeconds ?? 10, $deadline);

        $filters = [];
        $requestsUsed = 0;
        $probeTruncated = false;
        $maxColumns = $probeMaxColumns ?? (int) config('statsio.data_ingestion.live_query.probe_max_columns', 20);
        $timeout = $probeRequestTimeoutSeconds ?? (int) config('statsio.data_ingestion.live_query.probe_request_timeout_seconds', 10);

        if ($baseCount !== null) {
            foreach (array_slice($this->prioritizeColumns($schema), 0, $maxColumns) as $column) {
                if ($requestsUsed >= self::MAX_PROBE_REQUESTS || ($deadline !== null && microtime(true) >= $deadline)) {
                    $probeTruncated = true;
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

                if ($schema[$column]['type']->isTemporal() && $requestsUsed < self::MAX_PROBE_REQUESTS
                    && ($deadline === null || microtime(true) < $deadline)) {
                    [$range, $used] = $this->detectDateRange(
                        $url, $method, $headers, $dataPath, $pagination,
                        $column, $value, $countPath, $baseCount, $timeout, $deadline,
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
            'probe_truncated' => $probeTruncated,
            'search_param' => $searchParam,
        ];
    }

    /** Nombre max de termes candidats essayés par nom de paramètre, pour borner le coût du sondage. */
    private const MAX_SEARCH_TERM_ATTEMPTS = 3;

    /**
     * @param  array<string, string>  $headers
     * @param  array<int, array<string, string|null>>  $sampleRows
     */
    private function detectSearchParam(string $url, string $method, array $headers, ?string $dataPath, array $sampleRows, int $timeout, ?float $deadline): ?string
    {
        if ($deadline !== null && microtime(true) >= $deadline) {
            return null;
        }

        $terms = array_slice($this->representativeSearchTerms($sampleRows), 0, self::MAX_SEARCH_TERM_ATTEMPTS);

        if ($terms === []) {
            // Aucun terme exploitable dans l'échantillon (que des valeurs non textuelles,
            // ex. uniquement des identifiants numériques) : impossible de vérifier qu'un
            // paramètre candidat filtre réellement quoi que ce soit, donc pas de sondage.
            return null;
        }

        foreach (self::SEARCH_PARAM_CANDIDATES as $candidate) {
            foreach ($terms as $term) {
                if ($deadline !== null && microtime(true) >= $deadline) {
                    return null;
                }

                try {
                    $page = $this->httpProbe->fetchPage($url, $method, $headers, [$candidate => $term], $timeout);
                    $records = $this->fetcher->extractRecordsFromBody($page['body'], $dataPath);
                    if ($this->recordsPlausiblyMatchTerm($records, $term)) {
                        return $candidate;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Mots candidats (lettres ASCII uniquement, 4+ caractères) piochés dans les
     * valeurs échantillonnées, pour maximiser les chances qu'un terme matche
     * réellement au moins une ligne côté upstream sans déclencher les
     * contraintes fréquentes de ce type d'API : longueur minimale (un
     * caractère unique comme 'a' est souvent rejeté), accents non supportés,
     * ou requête "trop large" quand le terme est un mot trop courant. On
     * trie par proximité à une longueur "sweet spot" empirique (~7
     * caractères) : ni un mot trivial (souvent trop fréquent), ni un terme
     * si long qu'il ne matche plus rien.
     *
     * @param  array<int, array<string, string|null>>  $sampleRows
     * @return string[]
     */
    private function representativeSearchTerms(array $sampleRows): array
    {
        $words = [];

        foreach ($sampleRows as $row) {
            foreach ($row as $value) {
                if (! is_string($value)) {
                    continue;
                }

                foreach (preg_split('/\s+/', trim($value)) as $word) {
                    $word = trim($word, ",.;:()[]{}\"'");
                    if (preg_match('/^[A-Za-z]{4,}$/', $word) === 1) {
                        $words[$word] = true;
                    }
                }
            }
        }

        $words = array_keys($words);
        usort($words, fn (string $a, string $b) => abs(mb_strlen($a) - 7) <=> abs(mb_strlen($b) - 7));

        return $words;
    }

    /**
     * Vérification de contenu, pas seulement de statut HTTP : un statut 200
     * ne prouve pas que le paramètre a réellement filtré quoi que ce soit —
     * beaucoup d'API ignorent silencieusement un paramètre inconnu et
     * renvoient la liste par défaut. On exige donc que chaque enregistrement
     * retourné contienne effectivement le terme recherché.
     *
     * @param  array<int, mixed>  $records
     */
    private function recordsPlausiblyMatchTerm(array $records, string $term): bool
    {
        if ($records === []) {
            return false;
        }

        $needle = mb_strtolower($term);

        foreach ($records as $record) {
            if (! is_array($record) || ! $this->recordContainsTerm($record, $needle)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<mixed>  $record
     */
    private function recordContainsTerm(array $record, string $needle): bool
    {
        foreach ($record as $value) {
            if (is_string($value) && str_contains(mb_strtolower($value), $needle)) {
                return true;
            }
            if (is_array($value) && $this->recordContainsTerm($value, $needle)) {
                return true;
            }
        }

        return false;
    }

    public function detectCountPath(array $baseBody): ?string
    {
        foreach (self::COUNT_PATH_CANDIDATES as $candidate) {
            if (is_numeric(Arr::get($baseBody, $candidate))) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Trie les colonnes candidates par probabilité décroissante d'être un
     * filtre utile, pour maximiser les chances d'en détecter malgré un budget
     * de colonnes réduit (contexte détection, §1.6 du plan) — sans effet
     * négatif en contexte création/reconfiguration où le budget est plus large.
     *
     * Priorité : (1) colonnes temporelles, (2) nom évoquant une convention de
     * filtre courante, (3) dimensions/géographie, (4) le reste, dans l'ordre du schéma.
     *
     * @param  array<string, array{type: ColumnTypeEnum, semantic_role?: string}>  $schema
     * @return string[]
     */
    private function prioritizeColumns(array $schema): array
    {
        $tiers = [[], [], [], []];

        foreach ($schema as $column => $meta) {
            $role = $meta['semantic_role'] ?? null;
            $tier = match (true) {
                $meta['type']->isTemporal() => 0,
                $this->looksLikeFilterName($column) => 1,
                in_array($role, ['dimension', 'geographic'], true) => 2,
                default => 3,
            };
            $tiers[$tier][] = $column;
        }

        return array_merge(...$tiers);
    }

    private function looksLikeFilterName(string $column): bool
    {
        $normalized = strtolower($column);

        foreach (self::FILTER_NAME_PATTERNS as $pattern) {
            if (str_starts_with($normalized, $pattern) || str_ends_with($normalized, $pattern)) {
                return true;
            }
        }

        return false;
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
            $query = $extraQuery;
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
    private function detectDateRange(string $url, string $method, array $headers, ?string $dataPath, array $pagination, string $column, string $representativeValue, ?string $countPath, float $baseCount, int $timeout, ?float $deadline = null): array
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
            if ($used >= 3 || ($deadline !== null && microtime(true) >= $deadline)) {
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
