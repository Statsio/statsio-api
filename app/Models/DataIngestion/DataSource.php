<?php

namespace App\Models\DataIngestion;

use App\Domain\DataIngestion\Enums\DataSourceRefreshFrequencyEnum;
use App\Domain\DataIngestion\Enums\DataSourceStatusEnum;
use App\Domain\DataIngestion\Enums\DataSourceTypeEnum;
use App\Models\User\User;
use Carbon\CarbonImmutable;
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
        'refresh_frequency',
        'last_refreshed_at',
        'next_refresh_at',
        'original_filename',
        'raw_storage_path',
        'file_size_bytes',
        'status',
        'error_message',
        'is_partial',
        'partial_reason',
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
            'refresh_frequency' => DataSourceRefreshFrequencyEnum::class,
            'last_refreshed_at' => 'datetime',
            'next_refresh_at' => 'datetime',
            'processed_at' => 'datetime',
            'api_config' => 'array',
            'categories' => 'array',
            'is_partial' => 'boolean',
        ];
    }

    /**
     * Recalcule `next_refresh_at` à partir de `refresh_frequency`, à appeler
     * après chaque fetch (manuel ou planifié) ou changement de fréquence.
     */
    public function scheduleNextRefresh(?CarbonImmutable $from = null): void
    {
        $from ??= CarbonImmutable::now();
        $next = $this->refresh_frequency->nextOccurrenceFrom($from);

        $this->update([
            'last_refreshed_at' => $from,
            'next_refresh_at' => $next,
        ]);
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
