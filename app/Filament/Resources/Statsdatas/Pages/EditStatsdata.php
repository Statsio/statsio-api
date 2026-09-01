<?php

namespace App\Filament\Resources\Statsdatas\Pages;

use App\Filament\Resources\Statsdatas\StatsdataResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStatsdata extends EditRecord
{
    protected static string $resource = StatsdataResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
