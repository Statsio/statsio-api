<?php

namespace App\Models\StatsData;

use App\Domain\StatsData\Enums\StatsDataSourceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StatsDataSource extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'stats_data_sources';

    protected $fillable = [
        'id',
        'stats_data_document_id',
        'type',
        'name',
        'sort_order',
        'manual_data',
        'file_disk',
        'file_path',
        'file_original_name',
        'file_mime',
        'file_size',
        'api_url',
        'api_key',
        'api_limit',
        'api_search_template',
        'api_search_field',
        'api_detected_content_type',
        'api_response_format',
        'api_response_root',
        'api_last_probed_at',
        'api_probe_status_code',
        'api_probe_ok',
        'normalization_mapping',
    ];

    protected $hidden = [
        'api_key',
    ];

    protected function casts(): array
    {
        return [
            'type' => StatsDataSourceType::class,
            'manual_data' => 'array',
            'sort_order' => 'integer',
            'file_size' => 'integer',
            'api_key' => 'encrypted',
            'api_limit' => 'integer',
            'api_search_template' => 'string',
            'api_search_field' => 'string',
            'api_last_probed_at' => 'datetime',
            'api_probe_status_code' => 'integer',
            'api_probe_ok' => 'boolean',
            'normalization_mapping' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StatsDataSource $row): void {
            if (empty($row->id)) {
                $row->id = (string) Str::uuid();
            }
        });

        static::deleting(function (StatsDataSource $row): void {
            if ($row->type === StatsDataSourceType::File
                && $row->file_disk && $row->file_path
                && Storage::disk($row->file_disk)->exists($row->file_path)) {
                Storage::disk($row->file_disk)->delete($row->file_path);
            }
        });
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(StatsDataDocument::class, 'stats_data_document_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(StatsDataNormalizedSnapshot::class, 'stats_data_source_id');
    }

    /**
     * Dernier snapshot par date (sans latestOfMany : PostgreSQL n’a pas max(uuid)).
     */
    public function latestSnapshot(): HasOne
    {
        $table = (new StatsDataNormalizedSnapshot)->getTable();

        return $this->hasOne(StatsDataNormalizedSnapshot::class, 'stats_data_source_id')
            ->whereRaw($table.'.id = (
                select s2.id
                from '.$table.' as s2
                where s2.stats_data_source_id = '.$table.'.stats_data_source_id
                order by s2.refreshed_at desc, s2.id desc
                limit 1
            )');
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $base = [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'sortOrder' => $this->sort_order,
        ];

        $typePayload = match ($this->type) {
            StatsDataSourceType::Manual => [
                'manualData' => $this->manual_data ?? [],
            ],
            StatsDataSourceType::File => [
                'file' => [
                    'originalName' => $this->file_original_name,
                    'mimeType' => $this->file_mime,
                    'size' => $this->file_size,
                ],
            ],
            StatsDataSourceType::Api => [
                'api' => [
                    'url' => $this->api_url,
                    'hasApiKey' => $this->api_key !== null && $this->api_key !== '',
                    'limit' => $this->api_limit,
                    'searchTemplate' => $this->api_search_template,
                    'searchField' => $this->api_search_field,
                    'detectedContentType' => $this->api_detected_content_type,
                    'responseFormat' => $this->api_response_format,
                    'responseRoot' => $this->api_response_root,
                    'lastProbedAt' => $this->api_last_probed_at?->toIso8601String(),
                    'probeOk' => $this->api_probe_ok,
                    'probeStatusCode' => $this->api_probe_status_code,
                ],
            ],
        };

        $last = $this->relationLoaded('latestSnapshot') && $this->latestSnapshot
            ? $this->latestSnapshot->toSummaryApiArray()
            : null;

        return array_merge($base, $typePayload, [
            'normalizationMapping' => $this->normalization_mapping ?? null,
            'lastSnapshot' => $last,
        ]);
    }

    public function applyProbeResult(array $probe): void
    {
        $this->api_detected_content_type = $probe['detectedContentType'] ?? null;
        $this->api_response_format = $probe['responseFormat'] ?? null;
        $this->api_response_root = $probe['suggestedResponseRoot'] ?? null;
        $this->api_last_probed_at = now();
        $this->api_probe_status_code = $probe['statusCode'] ?? null;
        $this->api_probe_ok = (bool) ($probe['ok'] ?? false);
    }
}
