<?php

namespace App\Filament\Resources\Articles\Pages;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\StudioContents\Support\PreparesStudioContent;
use Filament\Resources\Pages\CreateRecord;

class CreateArticle extends CreateRecord
{
    use PreparesStudioContent;

    protected static string $resource = ArticleResource::class;
}
