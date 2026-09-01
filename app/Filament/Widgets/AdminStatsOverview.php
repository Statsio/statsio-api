<?php

namespace App\Filament\Widgets;

use App\Domain\Support\Enums\ContactMessageStatusEnum;
use App\Models\Channel\Channel;
use App\Models\Support\ContactMessage;
use App\Models\Tv\TvChannel;
use App\Models\User\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminStatsOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $trashedUsers = User::onlyTrashed()->count();

        return [
            Stat::make('Utilisateurs', (string) User::count())
                ->description($trashedUsers > 0 ? "{$trashedUsers} supprimé(s)" : 'Comptes actifs')
                ->color('primary'),

            Stat::make('Admins', (string) User::where('is_admin', true)->count()),

            Stat::make('Chaînes éditoriales', (string) Channel::count()),

            Stat::make('Chaînes TV', (string) TvChannel::count()),

            Stat::make(
                'Messages contact à traiter',
                (string) ContactMessage::where('status', ContactMessageStatusEnum::NEW->value)->count(),
            )->color('warning'),
        ];
    }
}
