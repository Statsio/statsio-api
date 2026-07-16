<?php

namespace App\Domain\Tv\Actions;

use App\Models\Tv\TvBroadcast;
use App\Models\Tv\TvChannel;
use App\Models\Tv\TvUserView;
use DateTimeImmutable;
use DateTimeZone;

class GetChannelSchedulesAction
{
    public function __construct(
        private readonly FetchEpgAction $fetchEpg,
        private readonly StoreBroadcastsFromEpgAction $storeBroadcasts,
    ) {}

    /**
     * @param string $date Y-m-d in Europe/Paris timezone
     * @return array<array{ channelId: string, programmes: array }>
     */
    public function execute(string $date): array
    {
        if (!$this->hasDataForDate($date)) {
            $channels = TvChannel::where('is_active', true)
                ->whereNotNull('epg_channel_id')
                ->get(['slug', 'epg_channel_id']);

            foreach ($channels as $channel) {
                $entries = $this->fetchEpg->execute($channel->epg_channel_id);
                if (!empty($entries)) {
                    $this->storeBroadcasts->execute($entries, $channel->slug, $date);
                }
            }
        }

        return $this->loadFromDatabase($date);
    }

    private function hasDataForDate(string $date): bool
    {
        $tz = new DateTimeZone('Europe/Paris');

        $dayStart = (new DateTimeImmutable($date . ' 00:00:00', $tz))->setTimezone(new DateTimeZone('UTC'));
        $dayEnd   = (new DateTimeImmutable($date . ' 23:59:59', $tz))->setTimezone(new DateTimeZone('UTC'));

        return TvBroadcast::whereBetween('start_at', [$dayStart, $dayEnd])->exists();
    }

    private function loadFromDatabase(string $date): array
    {
        $tz = new DateTimeZone('Europe/Paris');

        $dayStart = (new DateTimeImmutable($date . ' 00:00:00', $tz))->setTimezone(new DateTimeZone('UTC'));
        $dayEnd   = (new DateTimeImmutable($date . ' 23:59:59', $tz))->setTimezone(new DateTimeZone('UTC'));

        $now = new DateTimeImmutable('now', $tz);

        $broadcasts = TvBroadcast::with(['program', 'audience'])
            ->whereBetween('start_at', [$dayStart, $dayEnd])
            ->orderBy('start_at')
            ->get();

        $wantCounts = TvUserView::whereIn('broadcast_id', $broadcasts->pluck('id'))
            ->where('type', 'will_watch')
            ->selectRaw('broadcast_id, count(*) as aggregate')
            ->groupBy('broadcast_id')
            ->pluck('aggregate', 'broadcast_id');

        $schedules = [];

        foreach ($broadcasts as $broadcast) {
            $channelId = $broadcast->tv_channel_id;

            if (!isset($schedules[$channelId])) {
                $schedules[$channelId] = [];
            }

            $startParis = $broadcast->start_at->setTimezone($tz);
            $endParis   = $broadcast->end_at->setTimezone($tz);

            $startMinutes = (int) $startParis->format('H') * 60 + (int) $startParis->format('i');
            $durationMins = max(1, (int) round(
                ($broadcast->end_at->timestamp - $broadcast->start_at->timestamp) / 60
            ));

            $isLive  = $now >= $broadcast->start_at && $now < $broadcast->end_at;
            $isAired = $now >= $broadcast->start_at;

            $score = $isAired
                ? ['type' => 'viewers', 'value' => $broadcast->audience?->viewers ?? 0]
                : ['type' => 'want', 'value' => (int) ($wantCounts[$broadcast->id] ?? 0)];

            $schedules[$channelId][] = [
                'broadcastId'     => $broadcast->id,
                'title'           => $broadcast->program->title,
                'startTime'       => $startParis->format('H:i'),
                'endTime'         => $endParis->format('H:i'),
                'startMinutes'    => $startMinutes,
                'durationMinutes' => $durationMins,
                'genres'          => $broadcast->program->type ? [$broadcast->program->type] : [],
                'summary'         => $broadcast->program->description,
                'isLive'          => $isLive,
                'mention'         => $broadcast->broadcast_type,
                'score'           => $score,
            ];
        }

        $result = [];
        foreach ($schedules as $channelId => $programmes) {
            $result[] = ['channelId' => $channelId, 'programmes' => $programmes];
        }

        return $result;
    }
}
