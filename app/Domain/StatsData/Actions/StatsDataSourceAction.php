<?php

namespace App\Domain\StatsData\Actions;

use App\Domain\StatsData\Enums\StatsDataSourceType;
use App\Domain\StatsData\Services\StatsDataApiProbeService;
use App\Models\StatsData\StatsDataSource;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StatsDataSourceAction
{
    public function __construct(
        private StatsDataDocumentAction $documents,
        private StatsDataApiProbeService $apiProbe
    ) {}

    /**
     * @return Collection<int, StatsDataSource>|null
     */
    public function listForUser(User $user, string $documentId): ?Collection
    {
        $doc = $this->documents->findOwnedOrNull($user, $documentId);
        if (! $doc) {
            return null;
        }

        return $doc->sources()->orderBy('sort_order')->orderBy('created_at')->get();
    }

    public function createForUser(User $user, string $documentId, array $data, ?UploadedFile $uploadedFile = null): ?StatsDataSource
    {
        $doc = $this->documents->findOwnedOrNull($user, $documentId);
        if (! $doc) {
            return null;
        }

        $type = StatsDataSourceType::from($data['type']);
        $nextOrder = (int) ($doc->sources()->max('sort_order') ?? 0) + 1;

        $source = new StatsDataSource([
            'id' => (string) Str::uuid(),
            'stats_data_document_id' => $doc->id,
            'type' => $type,
            'name' => $data['name'] ?? null,
            'sort_order' => $nextOrder,
        ]);

        match ($type) {
            StatsDataSourceType::Manual => $source->manual_data = $data['manual_data'] ?? [],
            StatsDataSourceType::File => $this->fillFileSource($source, $doc->id, $uploadedFile),
            StatsDataSourceType::Api => $this->fillApiSource($source, $data, (bool) ($data['verify'] ?? false)),
        };

        $source->save();

        return $source->fresh();
    }

    public function updateForUser(User $user, string $documentId, string $sourceId, array $data, ?UploadedFile $uploadedFile = null): ?StatsDataSource
    {
        $source = $this->findOwnedSourceOrNull($user, $documentId, $sourceId);
        if (! $source) {
            return null;
        }

        if (array_key_exists('name', $data)) {
            $source->name = $data['name'];
        }
        if (array_key_exists('sort_order', $data)) {
            $source->sort_order = (int) $data['sort_order'];
        }

        match ($source->type) {
            StatsDataSourceType::Manual => $this->patchManual($source, $data),
            StatsDataSourceType::File => $this->patchFile($source, $source->stats_data_document_id, $data, $uploadedFile),
            StatsDataSourceType::Api => $this->patchApi($source, $data),
        };

        $source->save();

        return $source->fresh();
    }

    private function patchManual(StatsDataSource $source, array $data): void
    {
        if (array_key_exists('manual_data', $data)) {
            $source->manual_data = $data['manual_data'] ?? [];
        }
    }

    private function patchFile(StatsDataSource $source, string $docId, array $data, ?UploadedFile $uploadedFile): void
    {
        if ($uploadedFile) {
            $this->deleteStoredFile($source);
            $this->fillFileSource($source, $docId, $uploadedFile);
        }
    }

    private function patchApi(StatsDataSource $source, array $data): void
    {
        if (array_key_exists('api_url', $data)) {
            $source->api_url = $data['api_url'];
        }
        if (array_key_exists('api_key', $data)) {
            $key = $data['api_key'];
            $source->api_key = ($key === null || $key === '') ? null : $key;
        }
        if (! empty($data['verify']) && $source->api_url) {
            $probe = $this->apiProbe->probe((string) $source->api_url, $source->api_key);
            $source->applyProbeResult($probe);
        }
        if (array_key_exists('response_root', $data)) {
            $root = $data['response_root'];
            $source->api_response_root = ($root === null || $root === '') ? null : $root;
        }
    }

    public function deleteForUser(User $user, string $documentId, string $sourceId): bool
    {
        $source = $this->findOwnedSourceOrNull($user, $documentId, $sourceId);
        if (! $source) {
            return false;
        }

        return (bool) $source->delete();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function probeForUser(User $user, string $documentId, string $url, ?string $apiKey): ?array
    {
        if ($this->documents->findOwnedOrNull($user, $documentId) === null) {
            return null;
        }

        return $this->apiProbe->probe($url, $apiKey);
    }

    public function findOwnedSourceOrNull(User $user, string $documentId, string $sourceId): ?StatsDataSource
    {
        if (! Str::isUuid($sourceId)) {
            return null;
        }

        $doc = $this->documents->findOwnedOrNull($user, $documentId);
        if (! $doc) {
            return null;
        }

        return StatsDataSource::query()
            ->where('stats_data_document_id', $doc->id)
            ->where('id', $sourceId)
            ->first();
    }

    private function fillFileSource(StatsDataSource $source, string $documentId, ?UploadedFile $file): void
    {
        if (! $file instanceof UploadedFile) {
            return;
        }

        $disk = 'local';
        $dir = 'stats-data/'.$documentId;
        $ext = $file->getClientOriginalExtension() ?: 'bin';
        $path = $file->storeAs($dir, $source->id.'.'.$ext, $disk);

        $source->file_disk = $disk;
        $source->file_path = $path;
        $source->file_original_name = $file->getClientOriginalName();
        $source->file_mime = $file->getMimeType() ?? $file->getClientMimeType();
        $source->file_size = $file->getSize();
    }

    private function fillApiSource(StatsDataSource $source, array $data, bool $verify): void
    {
        $source->api_url = $data['api_url'] ?? null;
        $key = $data['api_key'] ?? null;
        $source->api_key = ($key === null || $key === '') ? null : $key;

        if ($verify && $source->api_url) {
            $probe = $this->apiProbe->probe($source->api_url, $source->api_key);
            $source->applyProbeResult($probe);
        }

        if (! empty($data['response_root'])) {
            $source->api_response_root = $data['response_root'];
        }
    }

    private function deleteStoredFile(StatsDataSource $source): void
    {
        if ($source->file_disk && $source->file_path
            && Storage::disk($source->file_disk)->exists($source->file_path)) {
            Storage::disk($source->file_disk)->delete($source->file_path);
        }
        $source->file_disk = null;
        $source->file_path = null;
        $source->file_original_name = null;
        $source->file_mime = null;
        $source->file_size = null;
    }
}
