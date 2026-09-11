<?php

namespace App\Filament\Resources\PromoCategories\Pages;

use App\Filament\Resources\PromoCategories\PromoCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPromoCategories extends ListRecords
{
    protected static string $resource = PromoCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
