<?php

namespace App\Filament\Resources\HelpCategories;

use App\Filament\Resources\HelpCategories\Pages\CreateHelpCategory;
use App\Filament\Resources\HelpCategories\Pages\EditHelpCategory;
use App\Filament\Resources\HelpCategories\Pages\ListHelpCategories;
use App\Filament\Resources\HelpCategories\Schemas\HelpCategoryForm;
use App\Filament\Resources\HelpCategories\Tables\HelpCategoriesTable;
use App\Models\Help\HelpCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HelpCategoryResource extends Resource
{
    protected static ?string $model = HelpCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Catégories d\'aide';

    protected static ?string $modelLabel = 'catégorie d\'aide';

    protected static ?string $pluralModelLabel = 'catégories d\'aide';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return HelpCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HelpCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHelpCategories::route('/'),
            'create' => CreateHelpCategory::route('/create'),
            'edit' => EditHelpCategory::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('articles');
    }
}
