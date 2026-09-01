<?php

namespace App\Filament\Resources\Channels\Support;

use App\Domain\Channel\Actions\ChannelAction;
use App\Domain\Channel\Enums\ChannelBadgeEnum;
use App\Models\Channel\Channel;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;

/**
 * Actions de modération d'une chaîne éditoriale, partagées entre la table
 * (actions de ligne) et la page d'édition (actions d'en-tête). Elles délèguent
 * toutes à App\Domain\Channel\Actions\ChannelAction — même logique que l'ancien
 * AdminEditorialChannelController.
 */
class ChannelModerationActions
{
    /** @return array<int, Action> */
    public static function make(): array
    {
        return [
            Action::make('suspend')
                ->label('Suspendre')
                ->icon('heroicon-o-pause-circle')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (Channel $record): bool => $record->status !== 'suspended')
                ->action(function (Channel $record): void {
                    app(ChannelAction::class)->suspendChannel($record);
                    Notification::make()->title('Chaîne suspendue (7 jours)')->success()->send();
                }),

            Action::make('ban')
                ->label('Bannir')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (Channel $record): bool => $record->status !== 'banned')
                ->action(function (Channel $record): void {
                    app(ChannelAction::class)->banChannel($record);
                    Notification::make()->title('Chaîne bannie')->success()->send();
                }),

            Action::make('activate')
                ->label('Réactiver')
                ->icon('heroicon-o-play-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Channel $record): bool => $record->status !== 'active')
                ->action(function (Channel $record): void {
                    app(ChannelAction::class)->activateChannel($record);
                    Notification::make()->title('Chaîne réactivée')->success()->send();
                }),

            Action::make('anonymize')
                ->label('Anonymiser')
                ->icon('heroicon-o-eye-slash')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (Channel $record): bool => $record->status !== 'anonymized')
                ->action(function (Channel $record): void {
                    app(ChannelAction::class)->anonymizeChannel($record);
                    Notification::make()->title('Chaîne anonymisée')->success()->send();
                }),

            Action::make('badges')
                ->label('Badges')
                ->icon('heroicon-o-check-badge')
                ->color('gray')
                ->fillForm(fn (Channel $record): array => [
                    'badges' => $record->channelBadges->pluck('slug')->all(),
                ])
                ->schema([
                    CheckboxList::make('badges')
                        ->label('Badges attribués')
                        ->options(collect(ChannelBadgeEnum::cases())
                            ->mapWithKeys(fn (ChannelBadgeEnum $c): array => [$c->value => ucfirst($c->value)])
                            ->all()),
                ])
                ->action(function (array $data, Channel $record): void {
                    app(ChannelAction::class)->syncBadges($record, $data['badges'] ?? []);
                    Notification::make()->title('Badges mis à jour')->success()->send();
                }),
        ];
    }
}
