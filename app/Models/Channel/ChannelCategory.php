<?php

namespace App\Models\Channel;

use App\Domain\Content\Enums\SubBrandEnum;
use App\Models\Concerns\FiltersBySubBrand;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ChannelCategory extends Model
{
    use FiltersBySubBrand;

    protected $fillable = ['slug', 'label', 'position', 'sub_brand'];

    protected $casts = [
        'position' => 'integer',
        'sub_brand' => SubBrandEnum::class,
    ];

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
