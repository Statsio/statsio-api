<?php

namespace App\Filament\Resources\StudioContents\Support;

use App\Models\StudioContent;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StudioContentTable
{
    public static function configure(Table $table, string $type): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->sortable()
                    ->limit(60),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'published' => 'Publié',
                        'draft' => 'Brouillon',
                        default => (string) $state,
                    })
                    ->color(fn (?string $state): string => $state === 'published' ? 'success' : 'gray'),
                TextColumn::make('visibility')
                    ->label('Visibilité')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'public' => 'Public',
                        'protege' => 'Protégé',
                        'private' => 'Privé',
                        default => (string) $state,
                    })
                    ->color(fn (?string $state): string => $state === 'public' ? 'info' : 'gray'),
                TextColumn::make('owner')
                    ->label('Publié par')
                    ->getStateUsing(function (StudioContent $record): string {
                        if ($record->published_as === 'channel') {
                            return $record->channel?->profile?->name
                                ? '📢 '.$record->channel->profile->name
                                : '📢 chaîne #'.$record->channel_id;
                        }

                        $name = trim(($record->user?->profile?->first_name ?? '').' '.($record->user?->profile?->last_name ?? ''));

                        return $name !== '' ? $name : ($record->user?->email ?? '—');
                    }),
                TextColumn::make('views_count')
                    ->label('Vues')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'draft' => 'Brouillon',
                        'published' => 'Publié',
                    ]),
                SelectFilter::make('visibility')
                    ->label('Visibilité')
                    ->options([
                        'public' => 'Public',
                        'protege' => 'Protégé',
                        'private' => 'Privé',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
