<?php

namespace App\Filament\Resources\HelpCategories\Pages;

use App\Filament\Resources\HelpCategories\HelpCategoryResource;
use App\Filament\Resources\HelpCategories\Support\GeneratesSlugFromName;
use App\Models\Help\HelpCategory;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditHelpCategory extends EditRecord
{
    use GeneratesSlugFromName;

    protected static string $resource = HelpCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (HelpCategory $record, DeleteAction $action): void {
                    if ($record->articles()->exists()) {
                        Notification::make()
                            ->title('Suppression impossible')
                            ->body('Des articles utilisent cette catégorie.')
                            ->danger()
                            ->send();
                        $action->halt();
                    }
                }),
        ];
    }
}
