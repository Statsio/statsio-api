<?php

namespace App\Domain\Content\Support;

use App\Models\StudioContent;

/**
 * DTO léger d'un contenu pour les pages listing publiques.
 * Pas de blocs, pages ni datasets complets.
 */
class StudioContentListing
{
    /** @var list<string> */
    public const FORMATS = ['enquete', 'decryptage', 'dossier', 'breve'];

    /** @var array<string, list<string>> */
    public const FORMAT_ALIASES = [
        'enquete' => ['enquete', 'enquête', 'Enquête'],
        'decryptage' => ['decryptage', 'décryptage', 'Décryptage'],
        'dossier' => ['dossier', 'Dossier'],
        'breve' => ['breve', 'brève', 'Brève'],
    ];

    /**
     * @param  array<string, mixed>|null  $survey  agrégats de participation (cf. SurveyListingAggregates), sondages uniquement
     * @param  bool  $hasParticipated  le visiteur a déjà répondu à ce sondage
     * @return array<string, mixed>
     */
    public static function make(
        StudioContent $content,
        bool $isFavorited = false,
        ?array $survey = null,
        bool $hasParticipated = false,
    ): array {
        // Les cartes de listing reflètent la version PUBLIÉE, pas le brouillon en cours.
        $content->applyPublishedPayload();

        $blocks = StudioContentBlocks::all($content);
        $categories = array_values(array_filter(
            is_array($content->categories) ? $content->categories : [],
            fn ($c) => is_string($c) && $c !== ''
        ));
        $format = self::extractFormat($categories);
        $tags = array_values(array_filter(
            $categories,
            fn ($c) => self::normalizeKey($c) !== $format
        ));
        $theme = $tags[0] ?? null;

        $isChannel = $content->published_as === 'channel' && $content->channel;
        $publisherName = self::publisherName($content, $isChannel);
        $logoUrl = $isChannel ? ($content->channel->profile?->logo_url ?: null) : null;

        $card = [
            'id' => (string) $content->id,
            'slug' => $content->slug,
            'title' => $content->title,
            'description' => $content->description,
            'type' => $content->type ?? 'statsdata',
            'thumbnail_url' => $content->getFirstMediaUrl('thumbnail') ?: null,
            'categories' => $categories,
            'category' => $theme,
            'format' => $format,
            'tags' => $tags,
            'reading_minutes' => StudioContentBlocks::readingMinutes($blocks),
            'linked_datasets_count' => StudioContentBlocks::linkedDatasetCount($blocks),
            'charts_count' => StudioContentBlocks::chartCount($blocks),
            'views_count' => (int) ($content->views_count ?? 0),
            'updated_at' => $content->updated_at?->toIso8601String(),
            'created_at' => $content->created_at?->toIso8601String(),
            'publisher' => [
                'name' => $publisherName,
                'initials' => self::initials($publisherName),
                'logo_url' => $logoUrl,
                'is_channel' => $isChannel,
                'verified' => $isChannel,
                'handle' => $isChannel ? ($content->channel->profile?->handle ?: null) : null,
            ],
            'is_favorited' => $isFavorited,
        ];

        if (($content->type ?? null) === 'survey') {
            $deadline = $content->response_deadline;
            $card = array_merge($card, [
                'survey_kind' => $content->survey_kind ?? 'single_question',
                'requires_identity_verification' => (bool) $content->requires_identity_verification,
                'response_deadline' => $deadline?->toIso8601String(),
                'is_closed' => $deadline !== null && $deadline->isPast(),
                'petition_goal' => $content->petition_goal,
                'petition_target' => $content->petition_target,
                'has_participated' => $hasParticipated,
                'responses_count' => (int) ($survey['responses_count'] ?? 0),
                'questions_count' => (int) ($survey['questions_count'] ?? StudioContentBlocks::formQuestionCount($blocks)),
                'estimated_minutes' => (int) ($survey['estimated_minutes'] ?? 1),
                'question_types' => $survey['question_types'] ?? StudioContentBlocks::formQuestionTypes($blocks),
                'primary_options' => $survey['primary_options'] ?? [],
                'question_previews' => $survey['question_previews'] ?? [],
            ]);
        }

        return $card;
    }

    /**
     * @param  list<string>  $categories
     */
    public static function extractFormat(array $categories): ?string
    {
        foreach ($categories as $category) {
            $key = self::normalizeKey($category);
            if (in_array($key, self::FORMATS, true)) {
                return $key;
            }
        }

        return null;
    }

    public static function normalizeKey(string $value): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $folded = strtolower($ascii !== false && $ascii !== '' ? $ascii : $value);

        return preg_replace('/[^a-z0-9]+/', '', $folded) ?? $folded;
    }

    /** Relations à charger avant make(). */
    public static function eagerLoads(): array
    {
        return ['user.profile', 'channel.profile', 'media', 'publishedVersion'];
    }

    private static function publisherName(StudioContent $content, bool $isChannel): string
    {
        if ($isChannel) {
            return $content->channel->profile?->name ?: 'Anonyme';
        }

        $profile = $content->user?->profile;
        $name = trim(($profile?->first_name ?? '').' '.($profile?->last_name ?? ''));

        return $name !== '' ? $name : 'Anonyme';
    }

    private static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }

        return $letters !== '' ? $letters : '?';
    }
}
