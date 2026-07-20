<?php

namespace App\Domain\Channel\Actions;

use App\Http\Controllers\StudioContentController;
use App\Models\Channel\ChannelProfile;
use App\Models\StudioContent;

class ChannelFeaturedContentAction
{
    private const SLOT_COLUMNS = [
        'article' => 'featured_article_id',
        'statsdata' => 'featured_statsdata_id',
        'survey' => 'featured_survey_id',
    ];

    public function getFeatured(ChannelProfile $profile): array
    {
        $profile->loadMissing(['featuredArticle', 'featuredStatsdata', 'featuredSurvey']);

        return [
            'article' => $profile->featuredArticle ? StudioContentController::format($profile->featuredArticle) : null,
            'statsdata' => $profile->featuredStatsdata ? StudioContentController::format($profile->featuredStatsdata) : null,
            'survey' => $profile->featuredSurvey ? StudioContentController::format($profile->featuredSurvey) : null,
        ];
    }

    /**
     * @param array{article?: int|null, statsdata?: int|null, survey?: int|null} $data
     */
    public function updateFeatured(ChannelProfile $profile, array $data): ChannelProfile
    {
        $updates = [];
        foreach (self::SLOT_COLUMNS as $key => $column) {
            if (array_key_exists($key, $data)) {
                $updates[$column] = $data[$key];
            }
        }

        if (! empty($updates)) {
            $profile->update($updates);
        }

        return $profile->fresh(['featuredArticle', 'featuredStatsdata', 'featuredSurvey']);
    }

    /**
     * A slot value is valid when null (clearing it), or when it points to the channel's own
     * published content of the expected type — owners can only feature what they've published.
     */
    public function validateSlot(?int $contentId, int $channelId, string $expectedType): bool
    {
        if ($contentId === null) {
            return true;
        }

        return StudioContent::where('id', $contentId)
            ->where('channel_id', $channelId)
            ->where('published_as', 'channel')
            ->where('status', 'published')
            ->where('type', $expectedType)
            ->exists();
    }
}
