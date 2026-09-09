<?php

namespace App\Filament\Resources\Offers\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OffersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('price_cents')
                    ->label('Prix')
                    ->formatStateUsing(fn (int $state, $record): string => $state === 0
                        ? 'Gratuit'
                        : number_format($state / 100, 2, ',', ' ').' €/'.$record->period)
                    ->sortable(),
                TextColumn::make('badge_label')
                    ->label('Pastille')
                    ->badge()
                    ->color('primary')
                    ->placeholder('—'),
                IconColumn::make('is_highlighted')
                    ->label('Mise en avant')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('max_channels')
                    ->label('Chaînes max.')
                    ->placeholder('Illimité')
                    ->toggleable(),
                TextColumn::make('max_channel_members')
                    ->label('Membres max.')
                    ->placeholder('Illimité')
                    ->toggleable(),
                IconColumn::make('allows_identity_verification')
                    ->label('Vérif. identité')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('position')
                    ->label('Position')
                    ->sortable(),
            ])
            ->defaultSort('position')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
