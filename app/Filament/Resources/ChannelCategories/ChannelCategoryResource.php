<?php

namespace App\Filament\Resources\ChannelCategories;

use App\Filament\Resources\ChannelCategories\Pages\EditChannelCategory;
use App\Filament\Resources\ChannelCategories\Pages\ListChannelCategories;
use App\Filament\Resources\ChannelCategories\Schemas\ChannelCategoryForm;
use App\Filament\Resources\ChannelCategories\Tables\ChannelCategoriesTable;
use App\Models\Channel\ChannelCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ChannelCategoryResource extends Resource
{
    protected static ?string $model = ChannelCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Chaînes';

    protected static ?string $navigationLabel = 'Catégories de chaîne';

    protected static ?string $modelLabel = 'catégorie de chaîne';

    protected static ?string $pluralModelLabel = 'catégories de chaîne';

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return ChannelCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChannelCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChannelCategories::route('/'),
            'edit' => EditChannelCategory::route('/{record}/edit'),
        ];
    }

    /** Les slugs sont adossés à ChannelCategoryEnum : pas de création/suppression libre. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('channelProfiles');
    }
}
