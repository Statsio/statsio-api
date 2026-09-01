<?php

namespace App\Filament\Resources\ContactMessages\Tables;

use App\Domain\Support\Enums\ContactMessageStatusEnum;
use App\Domain\Support\Enums\ContactReasonEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContactMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('reason')
                    ->label('Motif')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof ContactReasonEnum ? $state->label() : (string) $state),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn ($state): string => match ($state instanceof ContactMessageStatusEnum ? $state->value : $state) {
                        ContactMessageStatusEnum::NEW->value => 'warning',
                        ContactMessageStatusEnum::IN_PROGRESS->value => 'info',
                        ContactMessageStatusEnum::RESOLVED->value => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => $state instanceof ContactMessageStatusEnum ? $state->label() : (string) $state),
                TextColumn::make('created_at')
                    ->label('Reçu le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('reason')
                    ->label('Motif')
                    ->options(collect(ContactReasonEnum::cases())
                        ->mapWithKeys(fn (ContactReasonEnum $c): array => [$c->value => $c->label()])
                        ->all()),
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options(collect(ContactMessageStatusEnum::cases())
                        ->mapWithKeys(fn (ContactMessageStatusEnum $c): array => [$c->value => $c->label()])
                        ->all()),
            ])
            ->recordActions([
                EditAction::make()->label('Traiter'),
                DeleteAction::make(),
            ]);
    }
}
