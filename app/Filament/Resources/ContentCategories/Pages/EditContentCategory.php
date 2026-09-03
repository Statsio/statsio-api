<?php

namespace App\Filament\Resources\ContentCategories\Pages;

use App\Filament\Resources\ContentCategories\ContentCategoryResource;
use App\Filament\Resources\ContentCategories\Support\GeneratesSlugFromName;
use App\Models\Content\ContentCategory;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditContentCategory extends EditRecord
{
    use GeneratesSlugFromName;

    protected static string $resource = ContentCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (ContentCategory $record, DeleteAction $action): void {
                    if ($record->dossiers()->exists()) {
                        Notification::make()
                            ->title('Suppression impossible')
                            ->body('Des dossiers utilisent cette catégorie.')
                            ->danger()
                            ->send();
                        $action->halt();
                    }
                }),
        ];
    }
}
