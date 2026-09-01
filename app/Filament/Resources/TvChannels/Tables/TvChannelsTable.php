<?php

namespace App\Filament\Resources\TvChannels\Tables;

use App\Models\Tv\TvChannel;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TvChannelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('N°')
                    ->numeric()
                    ->sortable(),
                ImageColumn::make('logo_url')
                    ->label('Logo')
                    ->height(28),
                TextColumn::make('display_name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('broadcasts_count')
                    ->label('Diffusions')
                    ->sortable(),
            ])
            ->defaultSort('number')
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (TvChannel $record, DeleteAction $action): void {
                        if ($record->broadcasts()->exists()) {
                            Notification::make()
                                ->title('Suppression impossible')
                                ->body('Des diffusions sont rattachées à cette chaîne.')
                                ->danger()
                                ->send();
                            $action->halt();
                        }
                    }),
            ]);
    }
}
