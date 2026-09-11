<?php

namespace App\Filament\Resources\PromoCategories\Pages;

use App\Filament\Resources\PromoCategories\PromoCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPromoCategory extends EditRecord
{
    protected static string $resource = PromoCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
