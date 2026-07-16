<?php

namespace App\Models\Channel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelDailyView extends Model
{
    protected $fillable = [
        'channel_id',
        'view_date',
        'views_count',
    ];

    protected $casts = [
        'view_date' => 'date',
        'views_count' => 'integer',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}
