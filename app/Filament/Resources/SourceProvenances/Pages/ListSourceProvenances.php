<?php

namespace App\Filament\Resources\SourceProvenances\Pages;

use App\Filament\Resources\SourceProvenances\SourceProvenanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSourceProvenances extends ListRecords
{
    protected static string $resource = SourceProvenanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
