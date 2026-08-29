<?php

namespace App\Domain\Ai\Support;

use App\Models\DataIngestion\Dataset;
use App\Models\DataIngestion\DataSource;
use App\Models\User\User;
use App\Services\DataIngestion\ColumnSemanticClassifier;

/**
 * Vue compacte et « model-friendly » des sources de données pour l'assistant :
 * datasets accessibles (les siens + sources publiques rattachées), catalogue
 * public, schéma d'un dataset. Ne renvoie jamais de secret d'API ni de dataset
 * complet — schéma + échantillons de valeurs uniquement.
 */
class StudioSourceReader
{
    private const SAMPLE_LIMIT = 12;

    public function __construct(private readonly ColumnSemanticClassifier $classifier) {}

    /**
     * Datasets que l'utilisateur peut lier à un bloc (prêts uniquement).
     *
     * @return array<int,array<string,mixed>>
     */
    public function accessibleDatasets(User $user): array
    {
        return Dataset::query()
            ->with(['columns', 'dataSource'])
            ->where(fn ($q) => $q
                ->where('user_id', $user->id)
                ->orWhereHas('dataSource.users', fn ($u) => $u->where('user_id', $user->id)))
            ->where('status', 'ready')
            ->latest()
            ->get()
            ->map(fn (Dataset $dataset) => $this->summariseDataset($dataset))
            ->values()
            ->all();
    }

    /**
     * Catalogue public Statsio (sources partagées non encore rattachées).
     *
     * @return array<int,array<string,mixed>>
     */
    public function publicCatalog(User $user, ?string $query = null, ?string $category = null, int $limit = 15): array
    {
        return DataSource::query()
            ->with(['provenance', 'dataset.columns'])
            ->where('visibility', 'public')
            ->where('status', 'ready')
            ->when($query, fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($query).'%']))
            ->when($category, fn ($q) => $q->whereJsonContains('categories', $category))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (DataSource $source) use ($user) {
                $dataset = $source->dataset;

                return [
                    'data_source_id' => $source->id,
                    'name' => $source->name,
                    'categories' => $source->categories ?? [],
                    'provenance' => $source->provenance?->name,
                    'already_attached' => $dataset !== null && $dataset->isAccessibleBy($user->id),
                    'dataset_id' => $dataset?->id,
                    'row_count' => $dataset?->row_count,
                    'columns' => $dataset
                        ? $dataset->columns->pluck('name')->take(20)->values()->all()
                        : [],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Schéma détaillé d'un dataset accessible, ou null.
     *
     * @return array<string,mixed>|null
     */
    public function datasetSchema(User $user, int $datasetId): ?array
    {
        $dataset = Dataset::with(['columns', 'dataSource'])->find($datasetId);

        if ($dataset === null || ! $dataset->isAccessibleBy($user->id)) {
            return null;
        }

        return $this->summariseDataset($dataset, withSamples: true);
    }

    /**
     * @return array<string,mixed>
     */
    private function summariseDataset(Dataset $dataset, bool $withSamples = false): array
    {
        $schemaForClassifier = [];
        foreach ($dataset->columns as $col) {
            $samples = is_array($col->sample_values) ? $col->sample_values : [];
            $schemaForClassifier[$col->name] = [
                'type' => $col->type,
                'nullable' => $col->nullable,
                'sample_values' => $samples,
                'distinct_count' => count(array_unique(array_filter($samples, fn ($v) => $v !== null), SORT_REGULAR)),
                'sampled_count' => count($samples),
            ];
        }

        $roles = $this->classifier->classify($schemaForClassifier);

        return [
            'dataset_id' => $dataset->id,
            'name' => $dataset->dataSource?->name ?? "dataset #{$dataset->id}",
            'row_count' => $dataset->row_count,
            'materialization' => $dataset->isLive() ? 'live' : 'snapshot',
            'columns' => $dataset->columns
                ->sortBy('column_order')
                ->map(function ($col) use ($roles, $withSamples) {
                    $samples = is_array($col->sample_values) ? $col->sample_values : [];

                    $entry = [
                        'name' => $col->name,
                        'type' => $col->type->value,
                        'role' => $roles[$col->name] ?? 'unknown',
                        'nullable' => $col->nullable,
                    ];

                    if ($withSamples) {
                        $entry['samples'] = array_slice(array_values($samples), 0, self::SAMPLE_LIMIT);
                    }

                    return $entry;
                })
                ->values()
                ->all(),
        ];
    }
}
