<?php

namespace App\Models\Channel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Badge extends Model
{
    protected $fillable = [
        'slug',
        'label',
    ];

    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'badge_channel');
    }
}
