<?php

namespace App\Models\Tv;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TvChannel extends Model
{
    protected $fillable = ['slug', 'number', 'display_name', 'epg_channel_id', 'logo_url', 'is_active'];

    protected $casts = [
        'number'    => 'integer',
        'is_active' => 'boolean',
    ];

    public function broadcasts(): HasMany
    {
        return $this->hasMany(TvBroadcast::class, 'tv_channel_id', 'slug');
    }
}
