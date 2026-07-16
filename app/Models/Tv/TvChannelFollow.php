<?php

namespace App\Models\Tv;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TvChannelFollow extends Model
{
    protected $fillable = ['user_id', 'channel_id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(TvChannel::class, 'channel_id', 'slug');
    }
}
