<?php

namespace App\Filament\Resources\PromoCategories\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PromoCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('infos')
                    ->label('Infos')
                    ->formatStateUsing(fn (?array $state): int => count($state ?? []))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('ticker_duration_seconds')
                    ->label('Ticker avant (s)')
                    ->sortable(),
                TextColumn::make('info_duration_seconds')
                    ->label('Durée / info (s)')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                IconColumn::make('always_visible')
                    ->label('Toujours affichée')
                    ->boolean(),
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
