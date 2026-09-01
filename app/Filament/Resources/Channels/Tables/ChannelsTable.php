<?php

namespace App\Filament\Resources\Channels\Tables;

use App\Domain\Channel\Actions\ChannelAction;
use App\Domain\Channel\Enums\ChannelStatusEnum;
use App\Filament\Resources\Channels\Support\ChannelModerationActions;
use App\Models\Channel\Channel;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ChannelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('profile.name')
                    ->label('Nom')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'profile',
                        fn (Builder $q) => $q
                            ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($search).'%'])
                            ->orWhereRaw('LOWER(handle) LIKE ?', ['%'.mb_strtolower($search).'%']),
                    )),
                TextColumn::make('profile.handle')
                    ->label('@handle'),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ChannelStatusEnum::ACTIVE->value => 'success',
                        ChannelStatusEnum::SUSPENDED->value => 'warning',
                        ChannelStatusEnum::BANNED->value, ChannelStatusEnum::ANONYMIZED->value => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('owners.email')
                    ->label('Propriétaire(s)')
                    ->listWithLineBreaks()
                    ->limitList(2),
                TextColumn::make('subscribers_count')
                    ->label('Abonnés')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Créée le')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(collect(ChannelStatusEnum::cases())
                        ->mapWithKeys(fn (ChannelStatusEnum $c): array => [$c->value => ucfirst($c->value)])
                        ->all()),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make(ChannelModerationActions::make())
                    ->label('Modération')
                    ->icon('heroicon-o-shield-check')
                    ->button(),
                DeleteAction::make()
                    ->using(fn (Channel $record): bool => app(ChannelAction::class)->deleteChannel($record)),
            ]);
    }
}
