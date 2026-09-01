<?php

namespace App\Filament\Resources\TvBroadcasts\Pages;

use App\Filament\Resources\TvBroadcasts\TvBroadcastResource;
use Filament\Resources\Pages\ListRecords;

class ListTvBroadcasts extends ListRecords
{
    protected static string $resource = TvBroadcastResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
