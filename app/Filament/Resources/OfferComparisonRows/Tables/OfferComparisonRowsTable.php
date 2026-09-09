<?php

namespace App\Filament\Resources\OfferComparisonRows\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OfferComparisonRowsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Libellé')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('hint')
                    ->label('Précision')
                    ->placeholder('—')
                    ->limit(40),
                TextColumn::make('free_value')
                    ->label('Freemium')
                    ->color('gray'),
                TextColumn::make('premium_value')
                    ->label('Premium')
                    ->color('primary'),
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
