<?php

namespace App\Filament\Resources\TvChannels\Pages;

use App\Filament\Resources\TvChannels\TvChannelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTvChannels extends ListRecords
{
    protected static string $resource = TvChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
