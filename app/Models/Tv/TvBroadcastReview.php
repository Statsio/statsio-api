<?php

namespace App\Models\Tv;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TvBroadcastReview extends Model
{
    protected $table = 'tv_broadcast_reviews';

    public $timestamps = false;

    protected $fillable = ['programme_id', 'broadcast_id', 'user_id', 'rating', 'comment'];

    protected $casts = [
        'rating'     => 'integer',
        'created_at' => 'datetime',
    ];

    public function programme(): BelongsTo
    {
        return $this->belongsTo(TvProgram::class, 'programme_id');
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
