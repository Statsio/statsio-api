<?php

namespace App\Filament\Resources\TvBroadcasts\Support;

use App\Models\Tv\TvAudience;
use App\Models\Tv\TvBroadcast;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

/**
 * Édition de l'audience d'une diffusion — équivalent de l'ancien
 * AdminBroadcastController::updateAudience (PATCH /tv/broadcasts/{id}/audience).
 */
class AudienceAction
{
    public static function make(): Action
    {
        return Action::make('audience')
            ->label('Audience')
            ->icon('heroicon-o-chart-bar')
            ->fillForm(fn (TvBroadcast $record): array => [
                'pda' => $record->audience?->pda,
                'rank' => $record->audience?->rank,
                'mediametrie_viewers' => $record->audience?->mediametrie_viewers,
            ])
            ->schema([
                TextInput::make('pda')
                    ->label('PdA (%)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(100),
                TextInput::make('rank')
                    ->label('Classement')
                    ->numeric()
                    ->minValue(1),
                TextInput::make('mediametrie_viewers')
                    ->label('Téléspectateurs (Médiamétrie)')
                    ->numeric()
                    ->minValue(0),
            ])
            ->action(function (array $data, TvBroadcast $record): void {
                TvAudience::updateOrCreate(
                    ['broadcast_id' => $record->id],
                    array_filter($data, fn ($v): bool => $v !== null && $v !== ''),
                );
                Notification::make()->title('Audience enregistrée')->success()->send();
            });
    }
}
