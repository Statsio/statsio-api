<?php

namespace App\Models;

use App\Models\Channel\Channel;
use App\Models\Studio\StudioBlockResponse;
use App\Models\User\User;
use App\Traits\HasMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudioContent extends Model
{
    use HasFactory, HasMedia;

    protected $fillable = [
        'user_id',
        'title',
        'type',
        'survey_kind',
        'requires_identity_verification',
        'petition_goal',
        'petition_target',
        'description',
        'status',
        'visibility',
        'slug',
        'pages',
        'blocks',
        'sections',
        'categories',
        'emoji',
        'coverage_type',
        'coverage_data',
        'published_as',
        'channel_id',
        'response_deadline',
    ];

    protected $casts = [
        'pages' => 'array',
        'blocks' => 'array',
        'sections' => 'array',
        'categories' => 'array',
        'coverage_data' => 'array',
        'views_count' => 'integer',
        'response_deadline' => 'datetime',
        'requires_identity_verification' => 'boolean',
        'petition_goal' => 'integer',
    ];

    /**
     * Get the user that owns the studio content.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the channel this content is published under (when published_as === 'channel').
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Réponses aux blocs de formulaire (votes de sondage / signatures de pétition).
     */
    public function blockResponses(): HasMany
    {
        return $this->hasMany(StudioBlockResponse::class);
    }
}
