<?php

namespace App\Models\DataIngestion;

use App\Domain\DataIngestion\Enums\DataSourceStatusEnum;
use App\Domain\DataIngestion\Enums\DataSourceTypeEnum;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DataSource extends Model
{
    protected $table = 'data_sources';

    protected $fillable = [
        'user_id',
        'name',
        'type',
        'source_kind',
        'api_config',
        'original_filename',
        'raw_storage_path',
        'file_size_bytes',
        'status',
        'error_message',
        'processed_at',
        'visibility',
        'categories',
        'provenance_id',
        'provenance_other_label',
    ];

    protected function casts(): array
    {
        return [
            'type' => DataSourceTypeEnum::class,
            'status' => DataSourceStatusEnum::class,
            'processed_at' => 'datetime',
            'api_config' => 'array',
            'categories' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dataset(): HasOne
    {
        return $this->hasOne(Dataset::class, 'data_source_id');
    }

    public function provenance(): BelongsTo
    {
        return $this->belongsTo(SourceProvenance::class, 'provenance_id');
    }

    /**
     * Users who have attached this (public) source to their own Studio sidebar,
     * without duplicating the underlying data — see AttachPublicDataSourceAction.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'data_source_user')->withTimestamps();
    }

    public function isOwnedBy(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    public function isAccessibleBy(int $userId): bool
    {
        return $this->isOwnedBy($userId) || $this->users()->where('user_id', $userId)->exists();
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => DataSourceStatusEnum::PROCESSING]);
    }

    public function markAsReady(): void
    {
        $this->update([
            'status' => DataSourceStatusEnum::READY,
            'processed_at' => now(),
            'error_message' => null,
        ]);
    }

    public function markAsFailed(string $reason): void
    {
        $this->update([
            'status' => DataSourceStatusEnum::FAILED,
            'error_message' => $reason,
        ]);
    }
}
