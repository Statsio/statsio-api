<?php

namespace App\Http\Controllers\Api\DataIngestion;

use App\Domain\DataIngestion\Actions\UploadDataSourceAction;
use App\Domain\DataIngestion\Exceptions\UnsupportedFileTypeException;
use App\Http\Controllers\Controller;
use App\Http\Requests\DataIngestion\UploadDataSourceRequest;
use App\Models\DataIngestion\DataSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataSourceController extends Controller
{
    public function __construct(
        private readonly UploadDataSourceAction $uploadAction,
    ) {}

    public function upload(UploadDataSourceRequest $request): JsonResponse
    {
        try {
            $dataSource = $this->uploadAction->execute(
                file: $request->file('file'),
                user: $request->user(),
                name: $request->input('name'),
            );

            return response()->json([
                'success' => true,
                'message' => 'Fichier reçu. Le traitement est en cours.',
                'data' => $this->formatDataSource($dataSource),
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

    public function index(Request $request): JsonResponse
    {
        $dataSources = DataSource::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $dataSources->map(fn ($ds) => $this->formatDataSource($ds)),
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
        if ($dataSource->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Non autorisé.'], 403);
        }

        $dataSource->load('dataset.columns');

        return response()->json([
            'success' => true,
            'data' => $this->formatDataSourceWithDataset($dataSource),
        ]);
    }

    private function formatDataSource(DataSource $dataSource): array
    {
        return [
            'id' => $dataSource->id,
            'name' => $dataSource->name,
            'type' => $dataSource->type->value,
            'original_filename' => $dataSource->original_filename,
            'file_size_bytes' => $dataSource->file_size_bytes,
            'status' => $dataSource->status->value,
            'error_message' => $dataSource->error_message,
            'processed_at' => $dataSource->processed_at?->toIso8601String(),
            'created_at' => $dataSource->created_at->toIso8601String(),
        ];
    }

    private function formatDataSourceWithDataset(DataSource $dataSource): array
    {
        $data = $this->formatDataSource($dataSource);

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
