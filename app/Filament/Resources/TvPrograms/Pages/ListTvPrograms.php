<?php

namespace App\Filament\Resources\TvPrograms\Pages;

use App\Filament\Resources\TvPrograms\TvProgramResource;
use Filament\Resources\Pages\ListRecords;

class ListTvPrograms extends ListRecords
{
    protected static string $resource = TvProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
