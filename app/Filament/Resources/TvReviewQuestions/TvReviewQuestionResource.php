<?php

namespace App\Filament\Resources\TvReviewQuestions;

use App\Filament\Resources\TvReviewQuestions\Pages\CreateTvReviewQuestion;
use App\Filament\Resources\TvReviewQuestions\Pages\EditTvReviewQuestion;
use App\Filament\Resources\TvReviewQuestions\Pages\ListTvReviewQuestions;
use App\Filament\Resources\TvReviewQuestions\Schemas\TvReviewQuestionForm;
use App\Filament\Resources\TvReviewQuestions\Tables\TvReviewQuestionsTable;
use App\Models\Tv\TvReviewQuestion;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class TvReviewQuestionResource extends Resource
{
    protected static ?string $model = TvReviewQuestion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static string|\UnitEnum|null $navigationGroup = 'TV';

    protected static ?string $navigationLabel = 'Questions d\'avis';

    protected static ?string $modelLabel = 'question d\'avis';

    protected static ?string $pluralModelLabel = 'questions d\'avis';

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return TvReviewQuestionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TvReviewQuestionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTvReviewQuestions::route('/'),
            'create' => CreateTvReviewQuestion::route('/create'),
            'edit' => EditTvReviewQuestion::route('/{record}/edit'),
        ];
    }
}
