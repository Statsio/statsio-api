<?php

namespace App\Filament\Resources\HelpArticles\Pages;

use App\Filament\Resources\HelpArticles\HelpArticleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHelpArticles extends ListRecords
{
    protected static string $resource = HelpArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
