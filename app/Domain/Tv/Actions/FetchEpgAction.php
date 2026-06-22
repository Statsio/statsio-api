<?php

namespace App\Domain\Tv\Actions;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FetchEpgAction
{
    private const API_URL = 'https://epg.pw/api/epg.json';
    private const CACHE_TTL = 21600; // 6h

    /**
     * Fetch EPG entries for a single channel from epg.pw JSON API.
     * Returns an array of entries sorted chronologically spanning ~2-3 days.
     *
     * @return array<array{title: string, desc: string, start_date: string}>
     */
    public function execute(string $epgChannelId): array
    {
        return Cache::remember("tv.epg.channel.{$epgChannelId}", self::CACHE_TTL, function () use ($epgChannelId) {
            $response = Http::timeout(15)->get(self::API_URL, ['channel_id' => $epgChannelId]);

            if (!$response->ok()) {
                return [];
            }

            return $response->json('epg_list', []);
        });
    }
}
