<?php

namespace App\Models\Tv;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TvBroadcastScore extends Model
{
    protected $table = 'tv_broadcast_question_scores';

    public $timestamps = false;

    protected $fillable = ['broadcast_id', 'question_id', 'user_id', 'score'];

    protected $casts = [
        'score'      => 'integer',
        'created_at' => 'datetime',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(TvReviewQuestion::class, 'question_id');
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(TvBroadcast::class, 'broadcast_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
