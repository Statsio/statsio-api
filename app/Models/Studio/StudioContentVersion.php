<?php

namespace App\Models\Studio;

use App\Models\Channel\Channel;
use App\Models\StudioContent;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Instantané figé d'un contenu Studio au moment d'une publication
 * (voir PublishStudioContentAction).
 */
class StudioContentVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'studio_content_id',
        'version',
        'title',
        'description',
        'coverage',
        'categories',
        'pages',
        'sections',
        'blocks',
        'published_as',
        'channel_id',
        'published_by_user_id',
        'created_at',
    ];

    protected $casts = [
        'version' => 'integer',
        'categories' => 'array',
        'pages' => 'array',
        'sections' => 'array',
        'blocks' => 'array',
        'created_at' => 'datetime',
    ];

    public function content(): BelongsTo
    {
        return $this->belongsTo(StudioContent::class, 'studio_content_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function publishedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by_user_id');
    }

    /**
     * Charge utile éditoriale de la version (à réinjecter dans le brouillon lors
     * d'une restauration, ou à servir au public).
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'coverage' => $this->coverage,
            'categories' => $this->categories ?? [],
            'pages' => $this->pages ?? [],
            'sections' => $this->sections ?? [],
            'blocks' => $this->blocks ?? [],
        ];
    }
}
