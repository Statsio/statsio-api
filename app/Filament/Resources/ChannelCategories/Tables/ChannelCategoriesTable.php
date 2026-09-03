<?php

namespace App\Filament\Resources\ChannelCategories\Tables;

use App\Domain\Content\Enums\SubBrandEnum;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ChannelCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Libellé')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('sub_brand')
                    ->label('Domaine')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (SubBrandEnum $state): string => $state->label())
                    ->color(fn (SubBrandEnum $state): string => $state === SubBrandEnum::All ? 'gray' : 'primary'),
                TextColumn::make('channel_profiles_count')
                    ->label('Chaînes')
                    ->sortable(),
                TextColumn::make('position')
                    ->label('Position')
                    ->sortable(),
            ])
            ->defaultSort('position')
            ->filters([
                SelectFilter::make('sub_brand')
                    ->label('Domaine')
                    ->options(SubBrandEnum::options()),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
