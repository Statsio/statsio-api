<?php

namespace App\Filament\Resources\TvBroadcasts\Tables;

use App\Filament\Resources\TvBroadcasts\Schemas\TvBroadcastForm;
use App\Filament\Resources\TvBroadcasts\Support\AudienceAction;
use App\Models\Tv\TvBroadcast;
use App\Models\Tv\TvChannel;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TvBroadcastsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('program.title')
                    ->label('Programme')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tv_channel_id')
                    ->label('Chaîne')
                    ->searchable(),
                TextColumn::make('start_at')
                    ->label('Diffusé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('broadcast_type')
                    ->label('Type')
                    ->formatStateUsing(fn (?string $state): string => $state
                        ? (TvBroadcastForm::BROADCAST_TYPES[$state] ?? $state)
                        : '—')
                    ->badge(),
                TextColumn::make('season')->label('S')->toggleable(),
                TextColumn::make('episode')->label('É')->toggleable(),
                TextColumn::make('audience.pda')
                    ->label('PdA')
                    ->formatStateUsing(fn ($state): string => $state !== null ? $state.' %' : '—'),
                TextColumn::make('audience.rank')
                    ->label('Rang'),
            ])
            ->defaultSort('start_at', 'desc')
            ->filters([
                SelectFilter::make('tv_channel_id')
                    ->label('Chaîne')
                    ->options(fn (): array => TvChannel::query()->orderBy('number')->pluck('display_name', 'slug')->all()),
                Filter::make('start_at')
                    ->schema([
                        DatePicker::make('from')->label('Du'),
                        DatePicker::make('until')->label('Au'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('start_at', '>=', $d))
                            ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('start_at', '<=', $d));
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                AudienceAction::make(),
                DeleteAction::make()
                    ->using(function (TvBroadcast $record): void {
                        $record->audience()->delete();
                        $record->userViews()->delete();
                        $record->delete();
                    }),
            ]);
    }
}
