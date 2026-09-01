<?php

namespace App\Filament\Resources\Surveys\Pages;

use App\Filament\Resources\StudioContents\Support\PreparesStudioContent;
use App\Filament\Resources\Surveys\SurveyResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSurvey extends CreateRecord
{
    use PreparesStudioContent;

    protected static string $resource = SurveyResource::class;
}
