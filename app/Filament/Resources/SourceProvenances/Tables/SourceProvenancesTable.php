<?php

namespace App\Filament\Resources\SourceProvenances\Tables;

use App\Models\DataIngestion\SourceProvenance;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SourceProvenancesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable(),
                TextColumn::make('slug')
                    ->color('gray'),
                TextColumn::make('data_sources_count')
                    ->label('Sources liées')
                    ->sortable(),
            ])
            ->defaultSort('position')
            ->reorderable('position')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (SourceProvenance $record, DeleteAction $action): void {
                        if ($record->dataSources()->exists()) {
                            Notification::make()
                                ->title('Suppression impossible')
                                ->body('Des sources de données utilisent cette provenance.')
                                ->danger()
                                ->send();
                            $action->halt();
                        }
                    }),
            ]);
    }
}
