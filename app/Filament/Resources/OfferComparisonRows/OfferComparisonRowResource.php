<?php

namespace App\Filament\Resources\OfferComparisonRows;

use App\Filament\Resources\OfferComparisonRows\Pages\CreateOfferComparisonRow;
use App\Filament\Resources\OfferComparisonRows\Pages\EditOfferComparisonRow;
use App\Filament\Resources\OfferComparisonRows\Pages\ListOfferComparisonRows;
use App\Filament\Resources\OfferComparisonRows\Schemas\OfferComparisonRowForm;
use App\Filament\Resources\OfferComparisonRows\Tables\OfferComparisonRowsTable;
use App\Models\OfferComparisonRow;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class OfferComparisonRowResource extends Resource
{
    protected static ?string $model = OfferComparisonRow::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';

    protected static string|\UnitEnum|null $navigationGroup = 'Offres';

    protected static ?string $navigationLabel = 'Comparatif des offres';

    protected static ?string $modelLabel = 'ligne du comparatif';

    protected static ?string $pluralModelLabel = 'lignes du comparatif';

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return OfferComparisonRowForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OfferComparisonRowsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOfferComparisonRows::route('/'),
            'create' => CreateOfferComparisonRow::route('/create'),
            'edit' => EditOfferComparisonRow::route('/{record}/edit'),
        ];
    }
}
