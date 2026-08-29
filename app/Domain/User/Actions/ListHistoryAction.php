<?php

namespace App\Domain\User\Actions;

use App\Domain\User\Support\StudioContentSummary;
use App\Models\User\User;
use App\Models\User\UserContentView;
use Illuminate\Support\Carbon;

class ListHistoryAction
{
    /**
     * Historique de consultation groupé par tranche temporelle
     * (Aujourd'hui / Cette semaine / Plus ancien), du plus récent au plus ancien.
     *
     * @return array<int,array{key:string,label:string,items:array<int,array<string,mixed>>}>
     */
    public function execute(User $user, int $limit = 100): array
    {
        $views = UserContentView::query()
            ->with(array_map(fn ($r) => "content.$r", StudioContentSummary::eagerLoads()))
            ->where('user_id', $user->id)
            ->whereNotNull('last_viewed_at')
            ->orderByDesc('last_viewed_at')
            ->limit($limit)
            ->get()
            ->filter(fn (UserContentView $v) => $v->content !== null);

        $startOfToday = Carbon::today();
        $startOfWeek = Carbon::now()->startOfWeek();

        $groups = [
            'today' => ['key' => 'today', 'label' => "Aujourd'hui", 'items' => []],
            'week' => ['key' => 'week', 'label' => 'Cette semaine', 'items' => []],
            'earlier' => ['key' => 'earlier', 'label' => 'Plus ancien', 'items' => []],
        ];

        foreach ($views as $view) {
            $viewedAt = $view->last_viewed_at;

            $bucket = match (true) {
                $viewedAt->greaterThanOrEqualTo($startOfToday) => 'today',
                $viewedAt->greaterThanOrEqualTo($startOfWeek) => 'week',
                default => 'earlier',
            };

            $groups[$bucket]['items'][] = StudioContentSummary::make($view->content) + [
                'viewed_at' => $viewedAt->toIso8601String(),
                'progress' => $view->progress,
                'view_count' => $view->view_count,
            ];
        }

        return array_values(array_filter($groups, fn ($g) => count($g['items']) > 0));
    }

    /**
     * Contenus en cours de lecture (progression 1-99), pour l'Aperçu.
     *
     * @return array<int,array<string,mixed>>
     */
    public function inProgress(User $user, int $limit = 4): array
    {
        return UserContentView::query()
            ->with(array_map(fn ($r) => "content.$r", StudioContentSummary::eagerLoads()))
            ->where('user_id', $user->id)
            ->whereBetween('progress', [1, 99])
            ->orderByDesc('last_viewed_at')
            ->limit($limit)
            ->get()
            ->filter(fn (UserContentView $v) => $v->content !== null)
            ->map(fn (UserContentView $v) => StudioContentSummary::make($v->content) + [
                'progress' => $v->progress,
                'viewed_at' => $v->last_viewed_at?->toIso8601String(),
            ])
            ->values()
            ->all();
    }
}
