<?php

namespace App\Filament\Resources\HelpArticles;

use App\Filament\Resources\HelpArticles\Pages\CreateHelpArticle;
use App\Filament\Resources\HelpArticles\Pages\EditHelpArticle;
use App\Filament\Resources\HelpArticles\Pages\ListHelpArticles;
use App\Filament\Resources\HelpArticles\RelationManagers\FeedbackRelationManager;
use App\Filament\Resources\HelpArticles\Schemas\HelpArticleForm;
use App\Filament\Resources\HelpArticles\Tables\HelpArticlesTable;
use App\Models\Help\HelpArticle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HelpArticleResource extends Resource
{
    protected static ?string $model = HelpArticle::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Support';

    protected static ?string $navigationLabel = 'Articles d\'aide';

    protected static ?string $modelLabel = 'article d\'aide';

    protected static ?string $pluralModelLabel = 'articles d\'aide';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return HelpArticleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HelpArticlesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHelpArticles::route('/'),
            'create' => CreateHelpArticle::route('/create'),
            'edit' => EditHelpArticle::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            FeedbackRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount([
            'feedback as helpful_yes_count' => fn (Builder $q) => $q->where('helpful', true),
            'feedback as helpful_no_count' => fn (Builder $q) => $q->where('helpful', false),
        ]);
    }
}
