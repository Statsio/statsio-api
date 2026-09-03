<?php

namespace App\Filament\Resources\ContentCategories\Tables;

use App\Domain\Content\Enums\SubBrandEnum;
use App\Models\Content\ContentCategory;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContentCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('sub_brand')
                    ->label('Sous-marque')
                    ->badge()
                    ->sortable()
                    ->formatStateUsing(fn (SubBrandEnum $state): string => $state->label())
                    ->color(fn (SubBrandEnum $state): string => $state === SubBrandEnum::All ? 'gray' : 'primary'),
                TextColumn::make('dossiers_count')
                    ->label('Dossiers')
                    ->sortable(),
                TextColumn::make('position')
                    ->label('Position')
                    ->sortable(),
            ])
            ->defaultSort('position')
            ->filters([
                SelectFilter::make('sub_brand')
                    ->label('Sous-marque')
                    ->options(SubBrandEnum::options()),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (ContentCategory $record, DeleteAction $action): void {
                        if ($record->dossiers()->exists()) {
                            Notification::make()
                                ->title('Suppression impossible')
                                ->body('Des dossiers utilisent cette catégorie.')
                                ->danger()
                                ->send();
                            $action->halt();
                        }
                    }),
            ]);
    }
}
