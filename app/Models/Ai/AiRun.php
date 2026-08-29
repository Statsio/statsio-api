<?php

namespace App\Models\Ai;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * L'exécution asynchrone de la boucle d'agent pour un message utilisateur : statut
 * poll-able par le front, puis patch d'ops à appliquer sur le store du Studio.
 */
class AiRun extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'ai_conversation_id',
        'ai_message_id',
        'status',
        'patch',
        'attached_dataset_ids',
        'assistant_message',
        'error',
        'usage',
    ];

    protected $casts = [
        'patch' => 'array',
        'attached_dataset_ids' => 'array',
        'usage' => 'array',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_DONE, self::STATUS_FAILED], true);
    }
}
