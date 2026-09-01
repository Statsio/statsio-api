<?php

namespace App\Filament\Resources\TvBroadcasts\Pages;

use App\Filament\Resources\TvBroadcasts\Support\AudienceAction;
use App\Filament\Resources\TvBroadcasts\TvBroadcastResource;
use App\Models\Tv\TvBroadcast;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTvBroadcast extends EditRecord
{
    protected static string $resource = TvBroadcastResource::class;

    protected function getHeaderActions(): array
    {
        return [
            AudienceAction::make(),
            DeleteAction::make()
                ->using(function (TvBroadcast $record): void {
                    $record->audience()->delete();
                    $record->userViews()->delete();
                    $record->delete();
                }),
        ];
    }
}
