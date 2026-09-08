<?php

namespace App\Domain\Content\Actions;

use App\Domain\Channel\Enums\ChannelStatusEnum;
use App\Domain\DataIngestion\Enums\DatasetStatusEnum;
use App\Models\Channel\Channel;
use App\Models\DataIngestion\Dataset;
use App\Models\StudioContent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Chiffres publics de la plateforme, affichés sur les pages vitrine
 * (accueil, à propos, écrans d'authentification). Compteurs agrégés,
 * mis en cache — aucune donnée nominative.
 *
 * @phpstan-type PlatformStats array{
 *     statsdata: int,
 *     articles: int,
 *     surveys: int,
 *     published_total: int,
 *     datasets: int,
 *     channels: int,
 *     contributors: int,
 *     last_published_at: ?string,
 * }
 */
class PublicPlatformStatsAction
{
    private const CACHE_KEY = 'public.platform.stats';

    private const CACHE_TTL = 300;

    /**
     * @return PlatformStats
     */
    public function execute(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $byType = StudioContent::query()
                ->where('status', 'published')
                ->selectRaw('type, COUNT(*) as aggregate')
                ->groupBy('type')
                ->pluck('aggregate', 'type');

            $statsdata = (int) ($byType['statsdata'] ?? 0);
            $articles = (int) ($byType['article'] ?? 0);
            $surveys = (int) ($byType['survey'] ?? 0);

            // Comme les listings publics (heroStats du catalogue), on s'appuie sur
            // `updated_at` plutôt que `last_published_at` pour la fraîcheur globale.
            $lastPublishedAt = StudioContent::query()
                ->where('status', 'published')
                ->max('updated_at');

            return [
                'statsdata' => $statsdata,
                'articles' => $articles,
                'surveys' => $surveys,
                'published_total' => $statsdata + $articles + $surveys,
                'datasets' => Dataset::query()->where('status', DatasetStatusEnum::READY->value)->count(),
                'channels' => Channel::query()->where('status', ChannelStatusEnum::ACTIVE->value)->count(),
                'contributors' => $this->contributorsCount(),
                'last_published_at' => $lastPublishedAt
                    ? Carbon::parse($lastPublishedAt)->toIso8601String()
                    : null,
            ];
        });
    }

    /**
     * Nombre de créateurs distincts ayant au moins un contenu publié
     * (à leur nom ou via une chaîne).
     */
    private function contributorsCount(): int
    {
        return StudioContent::query()
            ->where('status', 'published')
            ->whereNotNull('user_id')
            ->distinct()
            ->count('user_id');
    }
}
