<?php

namespace App\Filament\Resources\HelpCategories\Pages;

use App\Filament\Resources\HelpCategories\HelpCategoryResource;
use App\Filament\Resources\HelpCategories\Support\GeneratesSlugFromName;
use Filament\Resources\Pages\CreateRecord;

class CreateHelpCategory extends CreateRecord
{
    use GeneratesSlugFromName;

    protected static string $resource = HelpCategoryResource::class;
}
