<?php

namespace App\Filament\Resources\ContentCategories\Pages;

use App\Filament\Resources\ContentCategories\ContentCategoryResource;
use App\Filament\Resources\ContentCategories\Support\GeneratesSlugFromName;
use Filament\Resources\Pages\CreateRecord;

class CreateContentCategory extends CreateRecord
{
    use GeneratesSlugFromName;

    protected static string $resource = ContentCategoryResource::class;
}
