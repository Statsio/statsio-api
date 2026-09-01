<?php

namespace App\Filament\Resources\TvReviewQuestions\Pages;

use App\Filament\Resources\TvReviewQuestions\TvReviewQuestionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTvReviewQuestions extends ListRecords
{
    protected static string $resource = TvReviewQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
