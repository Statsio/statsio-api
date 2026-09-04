<?php

namespace App\Domain\Tv\Actions;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchEpgAction
{
    private const API_URL = 'https://epg.pw/api/epg.json';

    private const CACHE_TTL = 21600; // 6h

    private const TIMEOUT = 10;

    /**
     * Fetch EPG entries for a single channel from epg.pw JSON API.
     * Returns an array of entries sorted chronologically spanning ~2-3 days.
     * Returns an empty array on any upstream failure (never throws) so a single
     * dead channel — or a full epg.pw outage — cannot 500 the schedules endpoint.
     *
     * @return array<array{title: string, desc: string, start_date: string}>
     */
    public function execute(string $epgChannelId): array
    {
        $cacheKey = "tv.epg.channel.{$epgChannelId}";

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && ! empty($cached)) {
            return $cached;
        }

        try {
            $response = Http::timeout(self::TIMEOUT)->get(self::API_URL, ['channel_id' => $epgChannelId]);
        } catch (ConnectionException $e) {
            Log::warning('EPG fetch failed (connection)', ['channel_id' => $epgChannelId, 'error' => $e->getMessage()]);

            return [];
        }

        if (! $response->ok()) {
            Log::warning('EPG fetch failed (status)', ['channel_id' => $epgChannelId, 'status' => $response->status()]);

            return [];
        }

        $list = $response->json('epg_list', []);

        // Only cache a real result — never let a transient empty/failed response
        // block a channel for the whole TTL.
        if (! empty($list)) {
            Cache::put($cacheKey, $list, self::CACHE_TTL);
        }

        return is_array($list) ? $list : [];
    }
}
