<?php

namespace App\Models\Channel;

use App\Domain\Channel\Enums\ChannelLinkTypeEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelProfileLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_profile_id',
        'type',
        'name',
        'url',
    ];

    protected $casts = [
        'type' => ChannelLinkTypeEnum::class,
    ];

    public function channelProfile()
    {
        return $this->belongsTo(ChannelProfile::class);
    }
}
