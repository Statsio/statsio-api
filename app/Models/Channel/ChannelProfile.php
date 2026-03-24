<?php

namespace App\Models\Channel;

use App\Traits\HasMedia;
use App\Domain\Channel\Enums\ChannelAgeRestrictionEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelProfile extends Model
{
    use HasFactory, HasMedia;

    protected $fillable = [
        'channel_id',
        'name',
        'description',
        'logo',
        'banner',
        'categories',
        'tags',
        'country',
        'is_featured',
        'subscriber_count',
        'view_count',
        'custom_color_primary',
        'custom_color_secondary',
        'age_restriction',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'subscriber_count' => 'integer',
        'view_count' => 'integer',
        'age_restriction' => ChannelAgeRestrictionEnum::class,
        'categories' => 'array',
        'tags' => 'array',
    ];

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function channelProfileLinks()
    {
        return $this->hasMany(ChannelProfileLink::class);
    }

    public function addMediaById(int $mediaId, string $collection): void
    {
        $media = \App\Models\Media::find($mediaId);
        if ($media) {
            $this->media()->save($media);
        }
    }

    public function getLogoMedia()
    {
        return $this->getFirstMedia('logo');
    }

    public function getBannerMedia()
    {
        return $this->getFirstMedia('banner');
    }

    public function getLogoUrl(): ?string
    {
        return $this->getFirstMediaUrl('logo');
    }

    public function getBannerUrl(): ?string
    {
        return $this->getFirstMediaUrl('banner');
    }

    /**
     * Check if content is suitable for a given age
     */
    public function isSuitableFor(int $age): bool
    {
        return $this->age_restriction->isSuitableFor($age);
    }

    /**
     * Check if this is adult content
     */
    public function isAdultContent(): bool
    {
        return $this->age_restriction->isAdultContent();
    }

    /**
     * Check if this is restricted content
     */
    public function isRestricted(): bool
    {
        return $this->age_restriction->isRestricted();
    }

    /**
     * Get age restriction display name
     */
    public function getAgeRestrictionDisplay(): string
    {
        return $this->age_restriction->getDisplayName();
    }

    /**
     * Get age restriction color for UI
     */
    public function getAgeRestrictionColor(): string
    {
        return $this->age_restriction->getColor();
    }

    /**
     * Get age restriction icon
     */
    public function getAgeRestrictionIcon(): string
    {
        return $this->age_restriction->getIcon();
    }

    /**
     * Get channel completion percentage
     */
    public function getProfileCompletionPercentage(): int
    {
        $requiredFields = ['name', 'description', 'categories'];
        $optionalFields = ['tags'];
        $allFields = array_merge($requiredFields, $optionalFields);

        $filledFields = 0;
        $totalFields = count($allFields);

        foreach ($allFields as $field) {
            if (!empty($this->$field)) {
                $filledFields++;
            }
        }

        // Add bonus points for logo and banner
        if ($this->getLogoUrl()) $filledFields++;
        if ($this->getBannerUrl()) $filledFields++;
        $totalFields += 2;

        return round(($filledFields / $totalFields) * 100);
    }
}
