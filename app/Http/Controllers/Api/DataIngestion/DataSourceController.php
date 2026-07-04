<?php

namespace App\Http\Controllers\Api\DataIngestion;

use App\Domain\DataIngestion\Actions\AttachPublicDataSourceAction;
use App\Domain\DataIngestion\Actions\CreateApiDataSourceAction;
use App\Domain\DataIngestion\Actions\UploadDataSourceAction;
use App\Domain\DataIngestion\Exceptions\ApiSourceFetchException;
use App\Domain\DataIngestion\Exceptions\UnsupportedFileTypeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DataIngestion\CreateApiDataSourceRequest;
use App\Http\Requests\DataIngestion\UploadDataSourceRequest;
use App\Models\DataIngestion\DataSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataSourceController extends Controller
{
    public function __construct(
        private readonly UploadDataSourceAction $uploadAction,
        private readonly CreateApiDataSourceAction $createApiAction,
        private readonly AttachPublicDataSourceAction $attachAction,
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
        try {
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

    private function formatDataSource(DataSource $dataSource, int $requestUserId): array
    {
        return [
            'id' => $dataSource->id,
            'name' => $dataSource->name,
            'type' => $dataSource->type->value,
            'source_kind' => $dataSource->source_kind,
            'original_filename' => $dataSource->original_filename,
            'file_size_bytes' => $dataSource->file_size_bytes,
            'status' => $dataSource->status->value,
            'error_message' => $dataSource->error_message,
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
