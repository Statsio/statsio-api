<?php

namespace App\Models\Tv;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TvBroadcast extends Model
{
    protected $fillable = ['program_id', 'tv_channel_id', 'start_at', 'end_at', 'season', 'episode'];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at'   => 'datetime',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(TvProgram::class, 'program_id');
    }

    public function audience(): HasOne
    {
        return $this->hasOne(TvAudience::class, 'broadcast_id');
    }

    public function userViews(): HasMany
    {
        return $this->hasMany(TvUserView::class, 'broadcast_id');
    }
}
