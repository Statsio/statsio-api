<?php

namespace App\Filament\Resources\Users\Tables;

use App\Domain\User\Enums\UserStatusEnum;
use App\Models\User\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Nom')
                    ->getStateUsing(fn (User $record): string => trim(
                        ($record->profile?->first_name ?? '').' '.($record->profile?->last_name ?? '')
                    ) ?: '—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('profile', function (Builder $q) use ($search): void {
                            $like = '%'.mb_strtolower($search).'%';
                            $q->whereRaw('LOWER(first_name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(last_name) LIKE ?', [$like]);
                        });
                    }),
                TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        UserStatusEnum::ACTIVE->value => 'success',
                        UserStatusEnum::SUSPENDED->value => 'warning',
                        UserStatusEnum::BANNED->value => 'danger',
                        default => 'gray',
                    }),
                IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label('Inscrit le')
                    ->dateTime('d/m/Y')
                    ->sortable(),
                TextColumn::make('deleted_at')
                    ->label('Supprimé le')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        UserStatusEnum::ACTIVE->value => 'Actif',
                        UserStatusEnum::SUSPENDED->value => 'Suspendu',
                        UserStatusEnum::BANNED->value => 'Banni',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (User $record): bool => $record->id === auth()->id()),
                RestoreAction::make(),
            ]);
    }
}
