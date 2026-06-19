<?php

namespace App\Models\Channel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ChannelCategory extends Model
{
    protected $fillable = ['slug', 'label', 'position'];

    public function channelProfiles(): BelongsToMany
    {
        return $this->belongsToMany(
            ChannelProfile::class,
            'channel_profile_categories',
            'channel_category_id',
            'channel_profile_id'
        );
    }
}
