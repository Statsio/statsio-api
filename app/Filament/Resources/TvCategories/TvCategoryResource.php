<?php

namespace App\Filament\Resources\TvCategories;

use App\Filament\Resources\TvCategories\Pages\CreateTvCategory;
use App\Filament\Resources\TvCategories\Pages\EditTvCategory;
use App\Filament\Resources\TvCategories\Pages\ListTvCategories;
use App\Filament\Resources\TvCategories\Schemas\TvCategoryForm;
use App\Filament\Resources\TvCategories\Tables\TvCategoriesTable;
use App\Models\Tv\TvCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TvCategoryResource extends Resource
{
    protected static ?string $model = TvCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'TV';

    protected static ?string $navigationLabel = 'Catégories TV';

    protected static ?string $modelLabel = 'catégorie TV';

    protected static ?string $pluralModelLabel = 'catégories TV';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TvCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TvCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTvCategories::route('/'),
            'create' => CreateTvCategory::route('/create'),
            'edit' => EditTvCategory::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('programs');
    }
}
