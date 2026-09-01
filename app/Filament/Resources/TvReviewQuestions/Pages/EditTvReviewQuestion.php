<?php

namespace App\Filament\Resources\TvReviewQuestions\Pages;

use App\Filament\Resources\TvReviewQuestions\TvReviewQuestionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTvReviewQuestion extends EditRecord
{
    protected static string $resource = TvReviewQuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
