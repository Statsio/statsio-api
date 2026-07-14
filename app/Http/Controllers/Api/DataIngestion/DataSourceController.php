<?php

namespace App\Http\Controllers\Api\DataIngestion;

use App\Domain\DataIngestion\Actions\AttachPublicDataSourceAction;
use App\Domain\DataIngestion\Actions\CreateApiDataSourceAction;
use App\Domain\DataIngestion\Actions\CreateLiveApiDataSourceAction;
use App\Domain\DataIngestion\Actions\RefreshApiDataSourceAction;
use App\Domain\DataIngestion\Actions\UpdateDataSourceAction;
use App\Domain\DataIngestion\Actions\UploadDataSourceAction;
use App\Domain\DataIngestion\Enums\DataSourceRefreshFrequencyEnum;
use App\Domain\DataIngestion\Exceptions\ApiSourceFetchException;
use App\Domain\DataIngestion\Exceptions\UnsupportedFileTypeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DataIngestion\CreateApiDataSourceRequest;
use App\Http\Requests\DataIngestion\UpdateDataSourceRequest;
use App\Http\Requests\DataIngestion\UploadDataSourceRequest;
use App\Models\DataIngestion\DataSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataSourceController extends Controller
{
    public function __construct(
        private readonly UploadDataSourceAction $uploadAction,
        private readonly CreateApiDataSourceAction $createApiAction,
        private readonly CreateLiveApiDataSourceAction $createLiveApiAction,
        private readonly AttachPublicDataSourceAction $attachAction,
        private readonly UpdateDataSourceAction $updateAction,
        private readonly RefreshApiDataSourceAction $refreshApiAction,
    ) {}

    public function upload(UploadDataSourceRequest $request): JsonResponse
    {
        try {
            $dataSource = $this->uploadAction->execute(
                file: $request->file('file'),
                user: $request->user(),
                name: $request->input('name'),
                visibility: $request->input('visibility', 'private'),
                categories: $request->input('categories', []),
                provenanceId: $request->input('provenance_id'),
                provenanceOtherLabel: $request->input('provenance_other_label'),
            );

            return response()->json([
                'success' => true,
                'message' => 'Fichier reçu. Le traitement est en cours.',
                'data' => $this->formatDataSource($dataSource, $request->user()->id),
            ], 202);
        } catch (UnsupportedFileTypeException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload du fichier.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function createFromApi(CreateApiDataSourceRequest $request): JsonResponse
    {
        $isLive = $request->input('materialization', 'snapshot') === 'live';

        try {
            if ($isLive) {
                $dataSource = $this->createLiveApiAction->execute(
                    user: $request->user(),
                    name: $request->input('name'),
                    url: $request->input('url'),
                    method: $request->input('method', 'GET'),
                    headers: $request->input('headers', []),
                    dataPath: $request->input('data_path'),
                    authType: $request->input('auth_type', 'none'),
                    visibility: $request->input('visibility', 'private'),
                    categories: $request->input('categories', []),
                    provenanceId: $request->input('provenance_id'),
                    provenanceOtherLabel: $request->input('provenance_other_label'),
                    pagination: $request->input('pagination', ['style' => 'none']),
                    queryMappingOverrides: $request->input('query_mapping'),
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Source API en direct créée.',
                    'data' => $this->formatDataSource($dataSource, $request->user()->id),
                ], 201);
            }

            $dataSource = $this->createApiAction->execute(
                user: $request->user(),
                name: $request->input('name'),
                url: $request->input('url'),
                method: $request->input('method', 'GET'),
                headers: $request->input('headers', []),
                dataPath: $request->input('data_path'),
                authType: $request->input('auth_type', 'none'),
                visibility: $request->input('visibility', 'private'),
                categories: $request->input('categories', []),
                provenanceId: $request->input('provenance_id'),
                provenanceOtherLabel: $request->input('provenance_other_label'),
                refreshFrequency: DataSourceRefreshFrequencyEnum::from($request->input('refresh_frequency', 'none')),
                pagination: $request->input('pagination', ['style' => 'none']),
            );

            return response()->json([
                'success' => true,
                'message' => 'Source API créée. Le traitement est en cours.',
                'data' => $this->formatDataSource($dataSource, $request->user()->id),
            ], 202);
        } catch (ApiSourceFetchException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la source API.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $dataSources = DataSource::where('user_id', $userId)
            ->orWhereHas('users', fn ($q) => $q->where('user_id', $userId))
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $dataSources->map(fn ($ds) => $this->formatDataSource($ds, $userId)),
            'meta' => [
                'total' => $dataSources->total(),
                'per_page' => $dataSources->perPage(),
                'current_page' => $dataSources->currentPage(),
                'last_page' => $dataSources->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, DataSource $dataSource): JsonResponse
    {
        if (! $dataSource->isAccessibleBy($request->user()->id)) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $dataSource->load('dataset.columns');

        return response()->json([
            'success' => true,
            'data' => $this->formatDataSourceWithDataset($dataSource, $request->user()->id),
        ]);
    }

    /**
     * Catalogue des sources publiques, avec recherche et filtre par catégorie.
     */
    public function publicCatalog(Request $request): JsonResponse
    {
        $query = DataSource::where('visibility', 'public')->with('provenance');

        if ($q = $request->query('q')) {
            $query->where('name', 'ilike', "%{$q}%");
        }

        if ($category = $request->query('category')) {
            $query->whereJsonContains('categories', $category);
        }

        $dataSources = $query->latest()->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $dataSources->map(fn ($ds) => $this->formatDataSource($ds, $request->user()->id)),
            'meta' => [
                'total' => $dataSources->total(),
                'per_page' => $dataSources->perPage(),
                'current_page' => $dataSources->currentPage(),
                'last_page' => $dataSources->lastPage(),
            ],
        ]);
    }

    /**
     * Rattache une source publique au compte de l'utilisateur courant (pas de duplication).
     */
    public function attachPublic(Request $request, DataSource $dataSource): JsonResponse
    {
        if ($dataSource->visibility !== 'public') {
            return response()->json(['success' => false, 'message' => 'Cette source n\'est pas publique.'], 403);
        }

        $this->attachAction->execute($dataSource, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Source ajoutée à vos sources.',
            'data' => $this->formatDataSource($dataSource->fresh(), $request->user()->id),
        ]);
    }

    /**
     * Rattache la source ou en met à jour les métadonnées / la configuration
     * (fichier remplacé pour une source "upload", URL et connexion reconfigurées
     * pour une source "api") — voir UpdateDataSourceAction pour la logique de
     * relance du pipeline d'ingestion.
     */
    public function update(UpdateDataSourceRequest $request, DataSource $dataSource): JsonResponse
    {
        if (! $dataSource->isOwnedBy($request->user()->id)) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        try {
            $updated = $this->updateAction->execute(
                $dataSource,
                $request->validated(),
                $request->file('file'),
            );

            return response()->json([
                'success' => true,
                'data' => $this->formatDataSourceWithDataset($updated, $request->user()->id),
            ]);
        } catch (ApiSourceFetchException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (UnsupportedFileTypeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la source.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Relance immédiatement le fetch d'une source "api" (bouton "Actualiser maintenant"),
     * sans changer sa configuration ni sa fréquence de planification.
     */
    public function refresh(Request $request, DataSource $dataSource): JsonResponse
    {
        if (! $dataSource->isOwnedBy($request->user()->id)) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        if ($dataSource->source_kind !== 'api') {
            return response()->json(['success' => false, 'message' => 'Seules les sources API peuvent être actualisées.'], 422);
        }

        if ($dataSource->isLive()) {
            return response()->json(['success' => false, 'message' => 'Une source en direct est toujours à jour, aucune actualisation n\'est nécessaire.'], 422);
        }

        try {
            $refreshed = $this->refreshApiAction->execute($dataSource);

            return response()->json([
                'success' => true,
                'message' => 'Actualisation lancée. Le traitement est en cours.',
                'data' => $this->formatDataSourceWithDataset($refreshed, $request->user()->id),
            ], 202);
        } catch (ApiSourceFetchException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => "Erreur lors de l'actualisation de la source.",
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function formatDataSource(DataSource $dataSource, int $requestUserId): array
    {
        $data = [
            'id' => $dataSource->id,
            'name' => $dataSource->name,
            'type' => $dataSource->type->value,
            'source_kind' => $dataSource->source_kind,
            'original_filename' => $dataSource->original_filename,
            'file_size_bytes' => $dataSource->file_size_bytes,
            'status' => $dataSource->status->value,
            'error_message' => $dataSource->error_message,
            'is_partial' => $dataSource->is_partial,
            'partial_reason' => $dataSource->partial_reason,
            'processed_at' => $dataSource->processed_at?->toIso8601String(),
            'created_at' => $dataSource->created_at->toIso8601String(),
            'visibility' => $dataSource->visibility,
            'categories' => $dataSource->categories ?? [],
            'provenance' => $dataSource->provenance ? [
                'id' => $dataSource->provenance->id,
                'slug' => $dataSource->provenance->slug,
                'name' => $dataSource->provenance->name,
            ] : null,
            'provenance_other_label' => $dataSource->provenance_other_label,
            'is_owner' => $dataSource->isOwnedBy($requestUserId),
        ];

        // La config API (URL, headers, éventuels secrets d'authentification)
        // n'est renvoyée qu'au propriétaire — un utilisateur qui a seulement
        // rattaché la source publique ne doit pas voir ces informations.
        if ($dataSource->source_kind === 'api' && $dataSource->isOwnedBy($requestUserId)) {
            $config = $dataSource->api_config ?? [];
            $data['materialization'] = $dataSource->materialization->value;
            $data['api_config'] = [
                'url' => $config['url'] ?? null,
                'method' => $config['method'] ?? 'GET',
                'auth_type' => $config['auth_type'] ?? 'none',
                'headers' => $config['headers'] ?? [],
                'data_path' => $config['data_path'] ?? null,
                'pagination' => $config['pagination'] ?? ['style' => 'none'],
            ];
            if ($dataSource->isLive()) {
                $data['query_mapping'] = $config['query_mapping'] ?? null;
            } else {
                $data['refresh_frequency'] = $dataSource->refresh_frequency->value;
                $data['last_refreshed_at'] = $dataSource->last_refreshed_at?->toIso8601String();
                $data['next_refresh_at'] = $dataSource->next_refresh_at?->toIso8601String();
            }
        }

        return $data;
    }

    private function formatDataSourceWithDataset(DataSource $dataSource, int $requestUserId): array
    {
        $data = $this->formatDataSource($dataSource, $requestUserId);

        if ($dataSource->dataset) {
            $data['dataset'] = [
                'id' => $dataSource->dataset->id,
                'name' => $dataSource->dataset->name,
                'row_count' => $dataSource->dataset->row_count,
                'status' => $dataSource->dataset->status->value,
                'progress' => $dataSource->dataset->progress,
                'columns' => $dataSource->dataset->columns->map(fn ($col) => [
                    'name' => $col->name,
                    'type' => $col->type->value,
                    'nullable' => $col->nullable,
                    'sample_values' => $col->sample_values,
                ])->values(),
            ];
        }

        return $data;
    }
}
