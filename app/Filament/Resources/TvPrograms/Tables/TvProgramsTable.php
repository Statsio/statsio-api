<?php

namespace App\Filament\Resources\TvPrograms\Tables;

use App\Models\Tv\TvChannel;
use App\Models\Tv\TvProgram;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TvProgramsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tv_channel_id')
                    ->label('Chaîne')
                    ->searchable(),
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('categories.name')
                    ->label('Catégories')
                    ->badge(),
                TextColumn::make('broadcasts_count')
                    ->label('Diffusions')
                    ->sortable(),
                IconColumn::make('is_tvstats_pick')
                    ->label('Coup de cœur')
                    ->boolean(),
            ])
            ->defaultSort('title')
            ->filters([
                SelectFilter::make('tv_channel_id')
                    ->label('Chaîne')
                    ->options(fn (): array => TvChannel::query()->orderBy('number')->pluck('display_name', 'slug')->all()),
                TernaryFilter::make('is_tvstats_pick')->label('Coup de cœur TVStats'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->using(function (TvProgram $record): void {
                        $record->broadcasts()->each(function ($broadcast): void {
                            $broadcast->audience()->delete();
                            $broadcast->userViews()->delete();
                            $broadcast->delete();
                        });
                        $record->delete();
                    }),
            ]);
    }
}
