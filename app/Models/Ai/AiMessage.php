<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un tour de conversation persisté (user / model / tool). Reflète le format neutre
 * App\Services\Ai\LlmMessage pour pouvoir rejouer l'historique dans la boucle d'agent.
 */
class AiMessage extends Model
{
    protected $fillable = [
        'ai_conversation_id',
        'role',
        'text',
        'tool_calls',
        'tool_results',
    ];

    protected $casts = [
        'tool_calls' => 'array',
        'tool_results' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }
}
