<?php

namespace App\Filament\Resources\TvCategories\Pages;

use App\Filament\Resources\TvCategories\Support\GeneratesSlugFromName;
use App\Filament\Resources\TvCategories\TvCategoryResource;
use App\Models\Tv\TvCategory;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditTvCategory extends EditRecord
{
    use GeneratesSlugFromName;

    protected static string $resource = TvCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (TvCategory $record, DeleteAction $action): void {
                    if ($record->programs()->exists()) {
                        Notification::make()
                            ->title('Suppression impossible')
                            ->body('Des programmes utilisent cette catégorie.')
                            ->danger()
                            ->send();
                        $action->halt();
                    }
                }),
        ];
    }
}
