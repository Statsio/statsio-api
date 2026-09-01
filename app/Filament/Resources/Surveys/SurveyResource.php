<?php

namespace App\Filament\Resources\Surveys;

use App\Filament\Resources\StudioContents\AbstractStudioContentResource;
use App\Filament\Resources\Surveys\Pages\CreateSurvey;
use App\Filament\Resources\Surveys\Pages\EditSurvey;
use App\Filament\Resources\Surveys\Pages\ListSurveys;
use BackedEnum;

class SurveyResource extends AbstractStudioContentResource
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'Sondages';

    protected static ?string $modelLabel = 'sondage';

    protected static ?string $pluralModelLabel = 'sondages';

    public static function contentType(): string
    {
        return 'survey';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSurveys::route('/'),
            'create' => CreateSurvey::route('/create'),
            'edit' => EditSurvey::route('/{record}/edit'),
        ];
    }
}
