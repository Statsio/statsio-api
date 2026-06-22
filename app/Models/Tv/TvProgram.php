<?php

namespace App\Models\Tv;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TvProgram extends Model
{
    protected $fillable = ['title', 'tv_channel_id', 'type', 'description'];

    public function broadcasts(): HasMany
    {
        return $this->hasMany(TvBroadcast::class, 'program_id');
    }
}
