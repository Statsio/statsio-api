<?php

namespace App\Models\Channel;

use App\Traits\HasMedia;
use App\Domain\Channel\Enums\ChannelAgeRestrictionEnum;
use App\Models\Channel\ChannelCategory;
use App\Models\StudioContent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ChannelProfile extends Model
{
    use HasFactory, HasMedia;

    protected $fillable = [
        'channel_id',
        'name',
        'handle',
        'description',
        'logo',
        'banner',
        'tags',
        'country',
        'is_featured',
        'view_count',
        'custom_color_primary',
        'custom_color_secondary',
        'age_restriction',
        'featured_article_id',
        'featured_statsdata_id',
        'featured_survey_id',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'view_count'  => 'integer',
        'age_restriction' => ChannelAgeRestrictionEnum::class,
        'tags' => 'array',
    ];

    protected $appends = ['subscriber_count', 'is_following', 'logo_url', 'banner_url', 'categories'];

    public function getSubscriberCountAttribute(): int
    {
        $channel = $this->relationLoaded('channel') ? $this->getRelation('channel') : $this->channel;

        if ($channel && array_key_exists('subscribers_count', $channel->getAttributes())) {
            return (int) $channel->subscribers_count;
        }

        return $channel?->subscribers()->count() ?? 0;
    }

    public function getIsFollowingAttribute(): bool
    {
        $channel = $this->relationLoaded('channel') ? $this->getRelation('channel') : $this->channel;

        if ($channel && array_key_exists('is_following', $channel->getAttributes())) {
            return (bool) $channel->is_following;
        }

        $user = Auth::guard('api')->user();

        return $user ? ($channel?->isUserSubscribed($user) ?? false) : false;
    }

    public function getCategoriesAttribute(): array
    {
        return $this->channelCategories->pluck('slug')->toArray();
    }

    public function channelCategories(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            ChannelCategory::class,
            'channel_profile_categories',
            'channel_profile_id',
            'channel_category_id'
        )->orderBy('position');
    }

    public function getLogoUrlAttribute(): ?string
    {
        $media = $this->media()->where('collection_name', 'logo')->latest()->first();
        return $media ? $media->getUrl() : null;
    }

    public function getBannerUrlAttribute(): ?string
    {
        $media = $this->media()->where('collection_name', 'banner')->latest()->first();
        return $media ? $media->getUrl() : null;
    }

    public function toArray(): array
    {
        $array = parent::toArray();
        if (isset($array['age_restriction']) && $array['age_restriction'] instanceof ChannelAgeRestrictionEnum) {
            $array['age_restriction'] = $array['age_restriction']->value;
        }
        return $array;
    }

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function channelProfileLinks()
    {
        return $this->hasMany(ChannelProfileLink::class);
    }

    public function featuredArticle(): BelongsTo
    {
        return $this->belongsTo(StudioContent::class, 'featured_article_id');
    }

    public function featuredStatsdata(): BelongsTo
    {
        return $this->belongsTo(StudioContent::class, 'featured_statsdata_id');
    }

    public function featuredSurvey(): BelongsTo
    {
        return $this->belongsTo(StudioContent::class, 'featured_survey_id');
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
