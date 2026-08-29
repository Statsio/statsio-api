<?php

namespace App\Models\Ai;

use App\Models\StudioContent;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Une conversation avec l'assistant IA du Studio, rattachée à un contenu éditorial.
 */
class AiConversation extends Model
{
    protected $fillable = [
        'user_id',
        'studio_content_id',
        'title',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function studioContent(): BelongsTo
    {
        return $this->belongsTo(StudioContent::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class)->orderBy('id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AiRun::class)->orderBy('id');
    }
}
