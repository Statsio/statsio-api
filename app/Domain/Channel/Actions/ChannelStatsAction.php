<?php

namespace App\Domain\Channel\Actions;

use App\Models\Channel\Channel;
use App\Models\Channel\ChannelDailyView;

class ChannelStatsAction
{
    public function __construct(
        private ChannelProfileAction $profileAction
    ) {}

    /**
     * Records one genuine visitor view of the channel's public page: bumps the
     * lifetime counter and today's daily rollup used for the 30-day sparkline.
     */
    public function recordView(Channel $channel): void
    {
        if ($channel->profile) {
            $this->profileAction->incrementViews($channel->profile);
        }

        $today = today()->toDateString();
        $row = ChannelDailyView::firstOrNew([
            'channel_id' => $channel->id,
            'view_date' => $today,
        ]);
        $row->views_count = ($row->views_count ?? 0) + 1;
        $row->save();
    }

    /**
     * Zero-filled daily view series for the last $days days, plus the total and
     * growth vs. the prior period of equal length.
     */
    public function getViewsSummary(Channel $channel, int $days = 30): array
    {
        $today = today();
        $start = $today->copy()->subDays($days - 1);

        $rows = $channel->dailyViews()
            ->whereBetween('view_date', [$start->toDateString(), $today->toDateString()])
            ->get()
            ->keyBy(fn (ChannelDailyView $row) => $row->view_date->toDateString());

        $series = [];
        $total = 0;
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $views = (int) ($rows[$date]->views_count ?? 0);
            $series[] = ['date' => $date, 'views' => $views];
            $total += $views;
        }

        $priorStart = $start->copy()->subDays($days);
        $priorEnd = $start->copy()->subDay();
        $priorTotal = (int) $channel->dailyViews()
            ->whereBetween('view_date', [$priorStart->toDateString(), $priorEnd->toDateString()])
            ->sum('views_count');

        return [
            'total' => $total,
            'growth_percent' => $this->growthPercent($total, $priorTotal),
            'series' => $series,
        ];
    }

    /**
     * New subscribers in the last $days days, plus growth vs. the prior period.
     */
    public function getSubscriberGrowth(Channel $channel, int $days = 7): array
    {
        $now = now();

        $current = $channel->subscribers()
            ->wherePivot('subscribed_at', '>=', $now->copy()->subDays($days))
            ->count();

        $prior = $channel->subscribers()
            ->wherePivot('subscribed_at', '>=', $now->copy()->subDays($days * 2))
            ->wherePivot('subscribed_at', '<', $now->copy()->subDays($days))
            ->count();

        return [
            'new_count' => $current,
            'growth_percent' => $this->growthPercent($current, $prior),
        ];
    }

    public function getStats(Channel $channel): array
    {
        return [
            'views' => $this->getViewsSummary($channel),
            'subscribers' => [
                'total' => $channel->subscribers()->count(),
                'growth' => $this->getSubscriberGrowth($channel),
            ],
            'team_member_count' => $channel->managementTeam()->count(),
            'lifetime_views' => $channel->profile?->view_count ?? 0,
        ];
    }

    private function growthPercent(int $current, int $previous): float
    {
        if ($previous === 0) {
            return $current > 0 ? 100.0 : 0.0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
