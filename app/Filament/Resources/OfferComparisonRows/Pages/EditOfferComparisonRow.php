<?php

namespace App\Filament\Resources\OfferComparisonRows\Pages;

use App\Filament\Resources\OfferComparisonRows\OfferComparisonRowResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOfferComparisonRow extends EditRecord
{
    protected static string $resource = OfferComparisonRowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
