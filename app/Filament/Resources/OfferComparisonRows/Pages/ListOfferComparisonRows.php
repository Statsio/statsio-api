<?php

namespace App\Filament\Resources\OfferComparisonRows\Pages;

use App\Filament\Resources\OfferComparisonRows\OfferComparisonRowResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOfferComparisonRows extends ListRecords
{
    protected static string $resource = OfferComparisonRowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
