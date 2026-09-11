<?php

namespace App\Filament\Resources\PremiumBlocks\Tables;

use App\Domain\Ai\BlockCatalog\StudioBlockCatalog;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PremiumBlocksTable
{
    public static function configure(Table $table): Table
    {
        $catalog = app(StudioBlockCatalog::class);

        return $table
            ->columns([
                TextColumn::make('block_type')
                    ->label('Bloc')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(fn (string $state): string => $catalog->get($state)['label'] ?? $state),
                TextColumn::make('category')
                    ->label('Catégorie')
                    ->getStateUsing(fn ($record) => $catalog->get($record->block_type)['category'] ?? '—')
                    ->color('gray'),
                TextColumn::make('offer.name')
                    ->label('Offre requise')
                    ->badge()
                    ->default('—'),
                TextColumn::make('created_at')
                    ->label('Marqué premium le')
                    ->dateTime('d/m/Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                DeleteAction::make()
                    ->label('Repasser en freemium')
                    ->requiresConfirmation(),
            ]);
    }
}
