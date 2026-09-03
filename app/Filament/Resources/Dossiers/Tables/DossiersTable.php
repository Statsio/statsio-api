<?php

namespace App\Filament\Resources\Dossiers\Tables;

use App\Domain\Content\Enums\SubBrandEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
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
                TextColumn::make('sub_brand')
                    ->label('Sous-marque')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (SubBrandEnum $state): string => $state->label())
                    ->color(fn (SubBrandEnum $state): string => $state === SubBrandEnum::All ? 'gray' : 'primary'),
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
                SelectFilter::make('sub_brand')
                    ->label('Sous-marque')
                    ->options(SubBrandEnum::options()),
                TernaryFilter::make('is_active')->label('Actif'),
                TernaryFilter::make('is_pinned')->label('Épinglé'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
