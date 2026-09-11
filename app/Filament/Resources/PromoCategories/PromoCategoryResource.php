<?php

namespace App\Filament\Resources\PromoCategories;

use App\Filament\Resources\PromoCategories\Pages\CreatePromoCategory;
use App\Filament\Resources\PromoCategories\Pages\EditPromoCategory;
use App\Filament\Resources\PromoCategories\Pages\ListPromoCategories;
use App\Filament\Resources\PromoCategories\Schemas\PromoCategoryForm;
use App\Filament\Resources\PromoCategories\Tables\PromoCategoriesTable;
use App\Models\Marketing\PromoCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class PromoCategoryResource extends Resource
{
    protected static ?string $model = PromoCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static string|\UnitEnum|null $navigationGroup = 'Contenus';

    protected static ?string $navigationLabel = 'Catégories de promotion';

    protected static ?string $modelLabel = 'catégorie de promotion';

    protected static ?string $pluralModelLabel = 'catégories de promotion';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PromoCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromoCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromoCategories::route('/'),
            'create' => CreatePromoCategory::route('/create'),
            'edit' => EditPromoCategory::route('/{record}/edit'),
        ];
    }
}
