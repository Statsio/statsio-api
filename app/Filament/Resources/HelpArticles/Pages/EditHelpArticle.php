<?php

namespace App\Filament\Resources\HelpArticles\Pages;

use App\Filament\Resources\HelpArticles\HelpArticleResource;
use App\Filament\Resources\HelpArticles\Support\GeneratesSlugFromTitle;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHelpArticle extends EditRecord
{
    use GeneratesSlugFromTitle;

    protected static string $resource = HelpArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
