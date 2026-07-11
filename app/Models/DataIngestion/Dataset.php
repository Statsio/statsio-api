<?php

namespace App\Models\DataIngestion;

use App\Domain\DataIngestion\Enums\DatasetStatusEnum;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Dataset extends Model
{
    protected $fillable = [
        'data_source_id',
        'user_id',
        'name',
        'description',
        'parquet_path',
        'row_count',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => DatasetStatusEnum::class,
            'row_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class, 'data_source_id');
    }

    public function columns(): HasMany
    {
        return $this->hasMany(DatasetColumn::class)->orderBy('column_order');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DatasetVersion::class)->orderByDesc('version_number');
    }

    public function latestVersion(): HasOne
    {
        return $this->hasOne(DatasetVersion::class)->latestOfMany('version_number');
    }

    public function markAsReady(string $parquetPath, int $rowCount): void
    {
        $this->update([
            'status' => DatasetStatusEnum::READY,
            'parquet_path' => $parquetPath,
            'row_count' => $rowCount,
        ]);
    }

    public function markAsFailed(): void
    {
        $this->update(['status' => DatasetStatusEnum::FAILED]);
    }

    public function isOwnedBy(int $userId): bool
    {
        return $this->user_id === $userId;
    }

    public function isLive(): bool
    {
        return $this->dataSource?->isLive() ?? false;
    }

    /**
     * True if the user owns this dataset's source, or has attached its
     * (public) source via the data_source_user pivot.
     */
    public function isAccessibleBy(int $userId): bool
    {
        return $this->isOwnedBy($userId)
            || $this->dataSource?->users()->where('user_id', $userId)->exists();
    }
}
