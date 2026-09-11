<?php

namespace App\Filament\Resources\HelpArticles\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class HelpArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Catégorie')
                    ->badge()
                    ->sortable(),
                TextColumn::make('views_count')
                    ->label('Vues')
                    ->sortable(),
                TextColumn::make('helpful_yes_count')
                    ->label('👍')
                    ->sortable(),
                TextColumn::make('helpful_no_count')
                    ->label('👎')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
                TextColumn::make('position')
                    ->label('Position')
                    ->sortable(),
            ])
            ->defaultSort('position')
            ->filters([
                SelectFilter::make('help_category_id')
                    ->label('Catégorie')
                    ->relationship('category', 'name'),
                TernaryFilter::make('is_active')->label('Actif'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
