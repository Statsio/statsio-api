<?php

namespace App\Filament\Resources\Dossiers\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class DossiersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('cover_path')
                    ->label('Image')
                    ->disk('public'),
                TextColumn::make('name')
                    ->label('Titre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('content_categories_count')
                    ->label('Catégories')
                    ->sortable(),
                TextColumn::make('studio_contents_count')
                    ->label('Contenus')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),
                IconColumn::make('is_pinned')
                    ->label('Épinglé')
                    ->boolean(),
                TextColumn::make('position')
                    ->label('Position')
                    ->sortable(),
            ])
            ->defaultSort('position')
            ->filters([
                TernaryFilter::make('is_active')->label('Actif'),
                TernaryFilter::make('is_pinned')->label('Épinglé'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
