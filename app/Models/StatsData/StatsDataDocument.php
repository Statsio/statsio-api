<?php

namespace App\Models\StatsData;

use App\Domain\StatsData\Enums\StatsDataVisibility;
use App\Models\Media;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo as EloquentBelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class StatsDataDocument extends Model
{
    use SoftDeletes;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'user_id',
        'title',
        'subtitle',
        'description',
        'categories',
        'tags',
        'cover_media_id',
        'visibility',
        'pages',
        'slug',
    ];

    protected function casts(): array
    {
        return [
            'visibility' => StatsDataVisibility::class,
            'pages' => 'array',
            'categories' => 'array',
            'tags' => 'array',
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

    public function coverMedia(): EloquentBelongsTo
    {
        return $this->belongsTo(Media::class, 'cover_media_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        $sources = $this->relationLoaded('sources')
            ? $this->sources
            : $this->sources()->orderBy('sort_order')->orderBy('created_at')->get();

        $cover = $this->relationLoaded('coverMedia') ? $this->coverMedia : $this->coverMedia()->first();
        $profile = $this->relationLoaded('user') ? ($this->user?->profile) : ($this->user()->with('profile')->first()?->profile);

        return [
            'id' => $this->id,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'description' => $this->description,
            'categories' => $this->categories ?? [],
            'tags' => $this->tags ?? [],
            'cover_media_id' => $this->cover_media_id,
            'cover_url' => $cover ? $cover->getUrl() : null,
            'visibility' => $this->visibility->value,
            'pages' => $this->pages ?? [],
            'dataSources' => $sources->map(fn (StatsDataSource $s) => $s->toApiArray())->values()->all(),
            'slug' => $this->slug,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'created_by' => [
                'id' => $this->user_id,
                'email' => $this->user?->email,
                'first_name' => $profile?->first_name,
                'last_name' => $profile?->last_name,
            ],
        ];
    }
}
