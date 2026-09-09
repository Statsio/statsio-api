<?php

namespace App\Filament\Resources\PremiumBlocks;

use App\Filament\Resources\PremiumBlocks\Pages\CreatePremiumBlock;
use App\Filament\Resources\PremiumBlocks\Pages\ListPremiumBlocks;
use App\Filament\Resources\PremiumBlocks\Schemas\PremiumBlockForm;
use App\Filament\Resources\PremiumBlocks\Tables\PremiumBlocksTable;
use App\Models\PremiumBlockType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

/**
 * Classification premium/freemium des blocs du Studio. Une ligne = un type de
 * bloc réservé à l'offre Premium (voir App\Domain\Ai\BlockCatalog\StudioBlockCatalog
 * pour le catalogue complet et App\Domain\Content\Support\PremiumBlockGate pour
 * l'application côté API).
 */
class PremiumBlockResource extends Resource
{
    protected static ?string $model = PremiumBlockType::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-lock-closed';

    protected static string|\UnitEnum|null $navigationGroup = 'Offres';

    protected static ?string $navigationLabel = 'Blocs premium';

    protected static ?string $modelLabel = 'bloc premium';

    protected static ?string $pluralModelLabel = 'blocs premium';

    protected static ?string $recordTitleAttribute = 'block_type';

    public static function form(Schema $schema): Schema
    {
        return PremiumBlockForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PremiumBlocksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPremiumBlocks::route('/'),
            'create' => CreatePremiumBlock::route('/create'),
        ];
    }
}
