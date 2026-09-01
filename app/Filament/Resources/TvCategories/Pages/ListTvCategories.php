<?php

namespace App\Filament\Resources\TvCategories\Pages;

use App\Filament\Resources\TvCategories\TvCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTvCategories extends ListRecords
{
    protected static string $resource = TvCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
