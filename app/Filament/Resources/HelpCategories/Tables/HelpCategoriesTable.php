<?php

namespace App\Filament\Resources\HelpCategories\Tables;

use App\Models\Help\HelpCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class HelpCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('icon')
                    ->label('Icône')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                ColorColumn::make('color')
                    ->label('Couleur'),
                TextColumn::make('articles_count')
                    ->label('Articles')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('position')
                    ->label('Position')
                    ->sortable(),
            ])
            ->defaultSort('position')
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (HelpCategory $record, DeleteAction $action): void {
                        if ($record->articles()->exists()) {
                            Notification::make()
                                ->title('Suppression impossible')
                                ->body('Des articles utilisent cette catégorie.')
                                ->danger()
                                ->send();
                            $action->halt();
                        }
                    }),
            ]);
    }
}
