<?php

namespace App\Filament\Resources\TvCategories\Tables;

use App\Models\Tv\TvCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TvCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                ColorColumn::make('color')
                    ->label('Couleur'),
                TextColumn::make('programs_count')
                    ->label('Programmes')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (TvCategory $record, DeleteAction $action): void {
                        if ($record->programs()->exists()) {
                            Notification::make()
                                ->title('Suppression impossible')
                                ->body('Des programmes utilisent cette catégorie.')
                                ->danger()
                                ->send();
                            $action->halt();
                        }
                    }),
            ]);
    }
}
