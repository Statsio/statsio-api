<?php

namespace App\Filament\Resources\TvChannels\Pages;

use App\Filament\Resources\TvChannels\Support\HandlesLogoUpload;
use App\Filament\Resources\TvChannels\TvChannelResource;
use App\Models\Tv\TvChannel;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTvChannel extends EditRecord
{
    use HandlesLogoUpload;

    protected static string $resource = TvChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (TvChannel $record, DeleteAction $action): void {
                    if ($record->broadcasts()->exists()) {
                        Notification::make()
                            ->title('Suppression impossible')
                            ->body('Des diffusions sont rattachées à cette chaîne.')
                            ->danger()
                            ->send();
                        $action->halt();
                    }
                }),
        ];
    }
}
