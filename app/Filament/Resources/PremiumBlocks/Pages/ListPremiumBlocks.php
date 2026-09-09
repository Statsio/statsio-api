<?php

namespace App\Filament\Resources\PremiumBlocks\Pages;

use App\Filament\Resources\PremiumBlocks\PremiumBlockResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPremiumBlocks extends ListRecords
{
    protected static string $resource = PremiumBlockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Marquer un bloc premium'),
        ];
    }
}
