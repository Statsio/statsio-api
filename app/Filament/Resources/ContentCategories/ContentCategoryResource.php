<?php

namespace App\Filament\Resources\ContentCategories;

use App\Filament\Resources\ContentCategories\Pages\CreateContentCategory;
use App\Filament\Resources\ContentCategories\Pages\EditContentCategory;
use App\Filament\Resources\ContentCategories\Pages\ListContentCategories;
use App\Filament\Resources\ContentCategories\Schemas\ContentCategoryForm;
use App\Filament\Resources\ContentCategories\Tables\ContentCategoriesTable;
use App\Models\Content\ContentCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContentCategoryResource extends Resource
{
    protected static ?string $model = ContentCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Contenus';

    protected static ?string $navigationLabel = 'Catégories de contenu';

    protected static ?string $modelLabel = 'catégorie de contenu';

    protected static ?string $pluralModelLabel = 'catégories de contenu';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ContentCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContentCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContentCategories::route('/'),
            'create' => CreateContentCategory::route('/create'),
            'edit' => EditContentCategory::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('dossiers');
    }
}
