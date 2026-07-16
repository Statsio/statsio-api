<?php

namespace App\Http\Controllers\Api\StatsData;

use App\Domain\DataIngestion\Exceptions\ApiSourceFetchException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DataIngestion\DetectApiStructureRequest;
use App\Services\DataIngestion\ApiStructureDetector;
use App\Services\DataIngestion\HttpProbeService;
use App\Services\DataIngestion\LiveApiSourceProber;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StatsDataSourceController extends Controller
{
    private const SAMPLE_ROWS_IN_RESPONSE = 20;

    public function __construct(
        private readonly HttpProbeService $httpProbe,
        private readonly ApiStructureDetector $structureDetector,
        private readonly LiveApiSourceProber $prober,
    ) {}

    /**
     * Test de connexion pour une source de type API (URL + méthode + headers optionnels).
     */
    public function probeConnection(Request $request)
    {
        $validated = $request->validate([
            'url' => ['required', 'url'],
            'method' => ['sometimes', 'string', 'in:GET,POST'],
            'headers' => ['sometimes', 'array'],
        ]);

        try {
            $this->httpProbe->probe(
                url: $validated['url'],
                method: $validated['method'] ?? 'GET',
                headers: $validated['headers'] ?? [],
            );

            return response()->json([
                'success' => true,
                'message' => 'Connexion réussie',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Impossible de joindre cette URL',
            ], 422);
        }
    }

    /**
     * Détection automatique de la structure d'une API à partir de sa seule URL
     * de base : méthode HTTP, enveloppe de réponse, pagination, filtres et
     * capacités analytiques — voir ApiStructureDetector/LiveApiSourceProber.
     * Purement calculatoire, aucune écriture DB.
     */
    public function detectStructure(DetectApiStructureRequest $request): \Illuminate\Http\JsonResponse
    {
        $url = $request->validated('url');
        $headers = $request->validated('headers') ?? [];

        $timeBudget = (int) config('statsio.data_ingestion.live_query.detect_time_budget_seconds', 10);
        $deadline = microtime(true) + $timeBudget;

        try {
            $structure = $this->structureDetector->detect($url, $headers, $deadline);
        } catch (ApiSourceFetchException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        if ($structure['data_path_confidence'] === 'not_found') {
            return response()->json([
                'success' => true,
                'partial' => true,
                'reason' => 'no_records_array_found',
                'method' => $structure['method'],
                'raw_sample' => $this->summarizeBody($structure['body']),
            ]);
        }

        $name = $this->deriveNameFromUrl($url);

        try {
            $probed = $this->prober->probe(
                $name, $url, $structure['method'], $headers, $structure['data_path'], $structure['pagination'],
                deadline: $deadline,
                probeMaxColumns: (int) config('statsio.data_ingestion.live_query.detect_probe_max_columns', 8),
                probeRequestTimeoutSeconds: (int) config('statsio.data_ingestion.live_query.detect_probe_request_timeout_seconds', 4),
                paginationConfidence: $structure['pagination_confidence'],
                // Réutilise la page déjà récupérée par ApiStructureDetector::detect() ci-dessus quand la
                // requête de première page serait de toute façon identique — s'épargne un aller-retour HTTP
                // complet, précieux sur une API volumineuse et lente (ex. Hub'Eau) face au budget de 10s.
                prefetchedPage: ['body' => $structure['body'], 'headers' => $structure['headers'], 'raw_size' => $structure['raw_size']],
                prefetchedResponseTimeMs: $structure['response_time_ms'],
            );
        } catch (ApiSourceFetchException $e) {
            return response()->json([
                'success' => true,
                'partial' => true,
                'reason' => 'probe_failed',
                'message' => $e->getMessage(),
                'method' => $structure['method'],
                'data_path' => $structure['data_path'],
                'pagination' => $structure['pagination'],
            ]);
        }

        $rows = is_array($probed['parsed']->rows) ? $probed['parsed']->rows : iterator_to_array($probed['parsed']->rows);

        return response()->json([
            'success' => true,
            'partial' => false,
            'method' => $structure['method'],
            'data_path' => $structure['data_path'],
            'data_path_confidence' => $structure['data_path_confidence'],
            'pagination' => $structure['pagination'],
            'pagination_confidence' => $structure['pagination_confidence'],
            'query_mapping' => $probed['query_mapping'],
            'schema' => $this->formatSchema($probed['schema']),
            'sample_rows' => array_slice($rows, 0, self::SAMPLE_ROWS_IN_RESPONSE),
            'row_count_hint' => $probed['row_count_hint'],
            'capabilities' => $probed['capabilities'],
        ]);
    }

    private function deriveNameFromUrl(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?: 'Source API';
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $lastSegment = $path !== '' ? Str::afterLast($path, '/') : null;

        return $lastSegment ? "{$host} — {$lastSegment}" : $host;
    }

    /**
     * @param  array<string, array{type: \App\Domain\DataIngestion\Enums\ColumnTypeEnum, nullable: bool, sample_values: array, semantic_role?: string}>  $schema
     */
    private function formatSchema(array $schema): array
    {
        $result = [];
        foreach ($schema as $name => $meta) {
            $result[] = [
                'name' => $name,
                'type' => $meta['type']->value,
                'nullable' => $meta['nullable'],
                'sample_values' => $meta['sample_values'],
                'semantic_role' => $meta['semantic_role'] ?? 'unknown',
            ];
        }

        return $result;
    }

    /**
     * Aperçu compact d'un corps de réponse pour lequel aucun tableau
     * d'enregistrements n'a été trouvé — pas la réponse brute complète
     * (potentiellement volumineuse), juste de quoi comprendre sa forme.
     */
    private function summarizeBody(array $body): array
    {
        $summary = [];
        foreach ($body as $key => $value) {
            $summary[$key] = match (true) {
                is_array($value) && array_is_list($value) => sprintf('[liste de %d élément(s)]', count($value)),
                is_array($value) => sprintf('{objet, %d clé(s)}', count($value)),
                is_string($value) && strlen($value) > 80 => substr($value, 0, 80).'…',
                default => $value,
            };
        }

        return $summary;
    }
}
