<?php

namespace App\Models\StatsData;

use App\Domain\StatsData\Enums\StatsDataSnapshotStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class StatsDataNormalizedSnapshot extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'stats_data_normalized_snapshots';

    protected $fillable = [
        'id',
        'stats_data_source_id',
        'rows',
        'row_count',
        'schema_version',
        'refreshed_at',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'rows' => 'array',
            'row_count' => 'integer',
            'schema_version' => 'integer',
            'refreshed_at' => 'datetime',
            'status' => StatsDataSnapshotStatus::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StatsDataNormalizedSnapshot $row): void {
            if (empty($row->id)) {
                $row->id = (string) Str::uuid();
            }
        });
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(StatsDataSource::class, 'stats_data_source_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toSummaryApiArray(): array
    {
        return [
            'id' => $this->id,
            'rowCount' => $this->row_count,
            'schemaVersion' => $this->schema_version,
            'refreshedAt' => $this->refreshed_at?->toIso8601String(),
            'status' => $this->status->value,
            'errorMessage' => $this->error_message,
        ];
    }
}
