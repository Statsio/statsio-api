<?php

namespace App\Filament\Resources\HelpArticles\Pages;

use App\Filament\Resources\HelpArticles\HelpArticleResource;
use App\Filament\Resources\HelpArticles\Support\GeneratesSlugFromTitle;
use Filament\Resources\Pages\CreateRecord;

class CreateHelpArticle extends CreateRecord
{
    use GeneratesSlugFromTitle;

    protected static string $resource = HelpArticleResource::class;
}
