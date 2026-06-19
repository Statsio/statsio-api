<?php

namespace App\Domain\Channel\Actions;

use App\Models\Channel\ChannelProfile;
use App\Models\Channel\ChannelProfileLink;
use App\Models\Channel\ChannelCategory;
use App\Domain\Channel\Enums\ChannelAgeRestrictionEnum;
use App\Domain\Channel\Enums\ChannelCategoryEnum;
use Illuminate\Http\UploadedFile;

class ChannelProfileAction
{
    public function createProfile(array $data): ChannelProfile
    {
        $profileData = [
            'channel_id'             => $data['channel_id'],
            'name'                   => $data['name'],
            'handle'                 => $data['handle'],
            'description'            => $data['description'] ?? null,
            'tags'                   => $data['tags'] ?? null,
            'country'                => $data['country'] ?? null,
            'is_featured'            => $data['is_featured'] ?? false,
            'custom_color_primary'   => $data['custom_color_primary'] ?? null,
            'custom_color_secondary' => $data['custom_color_secondary'] ?? null,
            'age_restriction'        => isset($data['age_restriction'])
                ? ChannelAgeRestrictionEnum::fromValue((int) $data['age_restriction'])
                : ChannelAgeRestrictionEnum::ALL_AGES,
        ];

        $channelProfile = ChannelProfile::create($profileData);

        // Associer les catégories via la table pivot
        $this->syncCategories($channelProfile, $data['categories'] ?? $data['category'] ?? null);

        if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
            $channelProfile->addMedia($data['logo'], 'channels/logos', 'logo');
        }

        if (isset($data['banner']) && $data['banner'] instanceof UploadedFile) {
            $channelProfile->addMedia($data['banner'], 'channels/banners', 'banner');
        }

        return $channelProfile->load(['channel', 'channelCategories']);
    }

    public function updateProfile(ChannelProfile $profile, array $data): ChannelProfile
    {
        $profileData = [];

        if (isset($data['name']))                   $profileData['name']                   = $data['name'];
        if (isset($data['handle']))                 $profileData['handle']                 = $data['handle'];
        if (isset($data['description']))            $profileData['description']            = $data['description'];
        if (isset($data['tags']))                   $profileData['tags']                   = $data['tags'];
        if (isset($data['country']))                $profileData['country']                = $data['country'];
        if (isset($data['is_featured']))            $profileData['is_featured']            = $data['is_featured'];
        if (isset($data['custom_color_primary']))   $profileData['custom_color_primary']   = $data['custom_color_primary'];
        if (isset($data['custom_color_secondary'])) $profileData['custom_color_secondary'] = $data['custom_color_secondary'];
        if (isset($data['age_restriction'])) {
            $profileData['age_restriction'] = ChannelAgeRestrictionEnum::fromValue((int) $data['age_restriction']);
        }

        if (!empty($profileData)) {
            $profile->update($profileData);
        }

        // Sync catégories si fournies
        if (isset($data['categories']) || isset($data['category'])) {
            $this->syncCategories($profile, $data['categories'] ?? $data['category']);
        }

        // Logo
        if (isset($data['logo']) && $data['logo'] instanceof UploadedFile) {
            $profile->media()->where('collection_name', 'logo')->get()->each(function ($m) {
                app(\App\Domain\Media\Actions\MediaAction::class)->delete($m);
            });
            $profile->addMedia($data['logo'], 'channels/logos', 'logo');
        }

        // Bannière
        if (isset($data['banner']) && $data['banner'] instanceof UploadedFile) {
            $profile->media()->where('collection_name', 'banner')->get()->each(function ($m) {
                app(\App\Domain\Media\Actions\MediaAction::class)->delete($m);
            });
            $profile->addMedia($data['banner'], 'channels/banners', 'banner');
        }

        return $profile->load(['channel', 'channelCategories']);
    }

    public function getProfileById(int $id): ?ChannelProfile
    {
        return ChannelProfile::with('channel')->find($id);
    }

    public function getAllProfiles(int $perPage = 15)
    {
        return ChannelProfile::with('channel')->paginate($perPage);
    }

    public function deleteProfile(ChannelProfile $profile): bool
    {
        // Supprimer les médias associés
        $profile->clearMedia();

        return $profile->delete();
    }

    public function updateStatistics(ChannelProfile $profile, array $stats): ChannelProfile
    {
        $statsData = [];

        if (isset($stats['subscriber_count'])) {
            $statsData['subscriber_count'] = $stats['subscriber_count'];
        }
        if (isset($stats['view_count'])) {
            $statsData['view_count'] = $stats['view_count'];
        }
        if (isset($stats['is_featured'])) {
            $statsData['is_featured'] = $stats['is_featured'];
        }

        $profile->update($statsData);

        return $profile->load('channel');
    }

    public function toggleFeatured(ChannelProfile $profile): ChannelProfile
    {
        $profile->update([
            'is_featured' => !$profile->is_featured
        ]);

        return $profile->load('channel');
    }

    public function incrementViews(ChannelProfile $profile): ChannelProfile
    {
        $profile->increment('view_count');

        return $profile->fresh(['channel']);
    }

    public function incrementSubscribers(ChannelProfile $profile): ChannelProfile
    {
        $profile->increment('subscriber_count');

        return $profile->fresh(['channel']);
    }

    public function decrementSubscribers(ChannelProfile $profile): ChannelProfile
    {
        if ($profile->subscriber_count > 0) {
            $profile->decrement('subscriber_count');
        }

        return $profile->fresh(['channel']);
    }

    /**
     * Add or update social media links
     */
    public function updateSocialLinks(ChannelProfile $profile, array $socialLinks): ChannelProfile
    {
        // Supprimer les anciens liens sociaux
        $profile->channelProfileLinks()->whereIn('type', [
            'twitter', 'instagram', 'youtube', 'tiktok', 'linkedin', 'facebook', 'discord'
        ])->delete();

        // Ajouter les nouveaux liens sociaux
        foreach ($socialLinks as $platform => $url) {
            if (!empty($url)) {
                $profile->channelProfileLinks()->create([
                    'type' => $platform,
                    'name' => ucfirst($platform),
                    'url' => $url,
                ]);
            }
        }

        return $profile->load(['channel', 'channelProfileLinks']);
    }

    /**
     * Add a single link to the profile
     */
    public function addLink(ChannelProfile $profile, string $type, string $name, string $url): ChannelProfile
    {
        $profile->channelProfileLinks()->create([
            'type' => $type,
            'name' => $name,
            'url' => $url,
        ]);

        return $profile->load(['channel', 'channelProfileLinks']);
    }

    /**
     * Remove a link from the profile
     */
    public function removeLink(ChannelProfile $profile, int $linkId): bool
    {
        return $profile->channelProfileLinks()->where('id', $linkId)->delete();
    }

    /**
     * Get all social media links for the profile
     */
    public function getSocialLinks(ChannelProfile $profile): array
    {
        $socialTypes = ['twitter', 'instagram', 'youtube', 'tiktok', 'linkedin', 'facebook', 'discord'];

        return $profile->channelProfileLinks()
                    ->whereIn('type', $socialTypes)
                    ->pluck('url', 'type')
                    ->toArray();
    }

    /**
     * Get all links for the profile
     */
    public function getAllLinks(ChannelProfile $profile): \Illuminate\Database\Eloquent\Collection
    {
        return $profile->channelProfileLinks()->get();
    }

    private function syncCategories(ChannelProfile $profile, mixed $categories): void
    {
        if ($categories === null) {
            $profile->channelCategories()->detach();
            return;
        }

        $slugs = is_array($categories) ? $categories : [$categories];
        $allowed = ChannelCategoryEnum::values();
        $slugs = array_values(array_unique(array_filter(
            $slugs,
            static fn ($s) => is_string($s) && in_array($s, $allowed, true)
        )));

        $ids = ChannelCategory::whereIn('slug', $slugs)->pluck('id')->toArray();
        $profile->channelCategories()->sync($ids);
    }
}
