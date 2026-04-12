<?php

namespace App\Models\StatsData;

use App\Domain\StatsData\Enums\StatsDataVisibility;
use App\Models\User\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StatsDataDocument extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'title',
        'subtitle',
        'visibility',
        'blocks',
        'slug',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => StatsDataVisibility::class,
            'blocks' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StatsDataDocument $doc): void {
            if (empty($doc->id)) {
                $doc->id = (string) Str::uuid();
            }
        });

        static::deleting(function (StatsDataDocument $doc): void {
            Storage::disk('local')->deleteDirectory('stats-data/'.$doc->id);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sources(): HasMany
    {
        return $this->hasMany(StatsDataSource::class, 'stats_data_document_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $sources = $this->relationLoaded('sources')
            ? $this->sources
            : $this->sources()->orderBy('sort_order')->orderBy('created_at')->get();

        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'visibility' => $this->visibility->value,
            'blocks' => $this->blocks ?? [],
            'dataSources' => $sources->map(fn (StatsDataSource $s) => $s->toApiArray())->values()->all(),
            'slug' => $this->slug,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
