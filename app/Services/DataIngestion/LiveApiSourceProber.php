<?php

namespace App\Services\DataIngestion;

use App\Domain\DataIngestion\Exceptions\ApiSourceFetchException;
use App\Services\DataIngestion\LiveQuery\FilterCapabilityProbe;
use App\Services\DataIngestion\LiveQuery\SourceCapabilityEvaluator;
use App\Services\DataIngestion\Parsers\JsonParser;
use Illuminate\Support\Arr;

/**
 * Étant donné une méthode/URL/dataPath/pagination déjà connus (détectés ou
 * fournis manuellement), effectue le sondage complet d'une source API "live" :
 * un appel HTTP sert à la fois de validation de connexion, d'échantillon pour
 * l'inférence de schéma, et de base pour la détection automatique du mapping
 * de filtres et des capacités analytiques.
 *
 * Extrait de CreateLiveApiDataSourceAction pour être réutilisé aussi bien par
 * le endpoint de détection en lecture seule (pas d'écriture DB) que par la
 * création/reconfiguration réelle d'une source.
 */
class LiveApiSourceProber
{
    private const SAMPLE_ROWS = 200;

    public function __construct(
        private readonly HttpProbeService $httpProbe,
        private readonly PaginatedApiFetcher $fetcher,
        private readonly SchemaInferenceService $schemaInferenceService,
        private readonly FilterCapabilityProbe $filterProbe,
        private readonly ColumnSemanticClassifier $semanticClassifier,
        private readonly SourceCapabilityEvaluator $capabilityEvaluator,
    ) {}

    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>  $pagination
     * @param  array<string, mixed>|null  $queryMappingOverrides  Corrections manuelles de l'utilisateur, prioritaires sur la détection automatique
     * @param  array{body: array, headers?: array, raw_size?: int}|null  $prefetchedPage  Page déjà récupérée par
     *                                                                                    ApiStructureDetector::detect() juste avant cet appel — réutilisée telle quelle si la requête de
     *                                                                                    première page serait de toute façon identique (aucun paramètre de pagination à ajouter), pour
     *                                                                                    économiser un aller-retour HTTP complet sur le budget de temps serré du endpoint de détection.
     * @return array{schema: array, parsed: \App\Domain\DataIngestion\DTOs\ParsedFileDTO, query_mapping: array, capabilities: array, row_count_hint: mixed, sample_body: array, response_time_ms: int}
     *
     * @throws ApiSourceFetchException
     */
    public function probe(
        string $name,
        string $url,
        string $method,
        array $headers,
        ?string $dataPath,
        array $pagination,
        ?array $queryMappingOverrides = null,
        int $sampleRows = self::SAMPLE_ROWS,
        ?float $deadline = null,
        int $probeMaxColumns = 20,
        int $probeRequestTimeoutSeconds = 10,
        string $paginationConfidence = 'guessed',
        ?array $prefetchedPage = null,
        ?int $prefetchedResponseTimeMs = null,
    ): array {
        $query = $this->fetcher->buildFirstPageQuery($pagination);

        if ($prefetchedPage !== null && $query === []) {
            $page = $prefetchedPage;
            $responseTimeMs = $prefetchedResponseTimeMs ?? 0;
        } else {
            $timeout = (int) config('statsio.data_ingestion.pagination.request_timeout_seconds', 15);
            $startedAt = microtime(true);
            try {
                $page = $this->httpProbe->fetchPage($url, $method, $headers, $query, $timeout);
            } catch (\Throwable $e) {
                throw new ApiSourceFetchException("Impossible de se connecter à l'API : {$e->getMessage()}", 0, $e);
            }
            $responseTimeMs = (int) round((microtime(true) - $startedAt) * 1000);
        }

        $records = $this->fetcher->extractRecordsFromBody($page['body'], $dataPath);
        $parsed = JsonParser::fromRecords($records, $sampleRows, $name);
        $schema = $this->schemaInferenceService->infer($parsed);

        $semanticRoles = $this->semanticClassifier->classify($schema);
        foreach ($semanticRoles as $column => $role) {
            $schema[$column]['semantic_role'] = $role;
        }

        $detectedMapping = $this->filterProbe->detect(
            $url, $method, $headers, $dataPath, $pagination, $page['body'], $schema, $parsed->rows,
            probeMaxColumns: $probeMaxColumns,
            probeRequestTimeoutSeconds: $probeRequestTimeoutSeconds,
            deadline: $deadline,
        );
        $queryMapping = $queryMappingOverrides
            ? array_replace_recursive($detectedMapping, $queryMappingOverrides)
            : $detectedMapping;

        $rowCountHint = $queryMapping['count_path'] ? Arr::get($page['body'], $queryMapping['count_path']) : null;

        $capabilities = $this->capabilityEvaluator->evaluate(
            $schema, $queryMapping, $pagination, $rowCountHint, $responseTimeMs, $paginationConfidence,
        );

        return [
            'schema' => $schema,
            'parsed' => $parsed,
            'query_mapping' => $queryMapping,
            'capabilities' => $capabilities,
            'row_count_hint' => $rowCountHint,
            'sample_body' => $page['body'],
            'response_time_ms' => $responseTimeMs,
        ];
    }
}
