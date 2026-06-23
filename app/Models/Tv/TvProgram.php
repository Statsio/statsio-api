<?php

namespace App\Models\Tv;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TvProgram extends Model
{
    protected $fillable = [
        'title', 'tv_channel_id', 'type', 'description',
        'image_url', 'youtube_url', 'is_tvstats_pick',
    ];

    protected $casts = [
        'is_tvstats_pick' => 'boolean',
    ];

    public function broadcasts(): HasMany
    {
        return $this->hasMany(TvBroadcast::class, 'program_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(TvCategory::class, 'tv_program_categories', 'program_id', 'category_id');
    }
}
