<?php

namespace App\Filament\Resources\TvCategories\Pages;

use App\Filament\Resources\TvCategories\Support\GeneratesSlugFromName;
use App\Filament\Resources\TvCategories\TvCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTvCategory extends CreateRecord
{
    use GeneratesSlugFromName;

    protected static string $resource = TvCategoryResource::class;
}
