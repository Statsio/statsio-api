<?php

namespace App\Models\Tv;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TvUserView extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'broadcast_id', 'type'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(TvBroadcast::class, 'broadcast_id');
    }
}
