<?php

namespace App\Filament\Resources\Statsdatas\Pages;

use App\Filament\Resources\Statsdatas\StatsdataResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStatsdatas extends ListRecords
{
    protected static string $resource = StatsdataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
