<?php

namespace App\Domain\StatsData\Actions;

use App\Domain\StatsData\Enums\StatsDataSnapshotStatus;
use App\Domain\StatsData\Services\StatsDataNormalizationService;
use App\Domain\StatsData\Services\StatsDataSourceParsedRootService;
use App\Models\StatsData\StatsDataNormalizedSnapshot;
use App\Models\StatsData\StatsDataSource;
use App\Models\User\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class RefreshNormalizedDatasetAction
{
    public function __construct(
        private StatsDataSourceAction $sources,
        private StatsDataSourceParsedRootService $parsedRoot,
        private StatsDataNormalizationService $normalizer,
    ) {}

    public function refreshForUser(User $user, string $documentId, string $sourceId): ?StatsDataNormalizedSnapshot
    {
        $source = $this->sources->findOwnedSourceOrNull($user, $documentId, $sourceId);
        if (! $source) {
            return null;
        }

        $mapping = $source->normalization_mapping;
        if (! is_array($mapping) || ! $this->mappingLooksConfigured($mapping)) {
            throw ValidationException::withMessages([
                'normalizationMapping' => [__('stats_data.normalization_mapping_required')],
            ]);
        }

        $max = (int) config('stats_data.max_snapshot_rows', 50_000);

        try {
            $root = $this->parsedRoot->build($source);
            $rows = $this->normalizer->normalize($mapping, $root, $max);
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            return $this->persistFailedSnapshot($source, (string) $e->getMessage());
        }

        return StatsDataNormalizedSnapshot::query()->create([
            'stats_data_source_id' => $source->id,
            'rows' => $rows,
            'row_count' => count($rows),
            'schema_version' => 1,
            'refreshed_at' => now(),
            'status' => StatsDataSnapshotStatus::Ok,
            'error_message' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $mapping
     */
    private function mappingLooksConfigured(array $mapping): bool
    {
        $keys = $mapping['keyFields'] ?? [];
        $vals = $mapping['valueFields'] ?? [];

        return (is_array($keys) && $keys !== [])
            || (is_array($vals) && $vals !== []);
    }

    private function persistFailedSnapshot(StatsDataSource $source, string $message): StatsDataNormalizedSnapshot
    {
        return StatsDataNormalizedSnapshot::query()->create([
            'stats_data_source_id' => $source->id,
            'rows' => [],
            'row_count' => 0,
            'schema_version' => 1,
            'refreshed_at' => now(),
            'status' => StatsDataSnapshotStatus::Failed,
            'error_message' => Str::limit($message, 2000),
        ]);
    }
}
