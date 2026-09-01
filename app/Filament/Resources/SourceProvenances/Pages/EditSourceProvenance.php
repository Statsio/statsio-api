<?php

namespace App\Filament\Resources\SourceProvenances\Pages;

use App\Filament\Resources\SourceProvenances\SourceProvenanceResource;
use App\Models\DataIngestion\SourceProvenance;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditSourceProvenance extends EditRecord
{
    protected static string $resource = SourceProvenanceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! empty($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->before(function (SourceProvenance $record, DeleteAction $action): void {
                    if ($record->dataSources()->exists()) {
                        Notification::make()
                            ->title('Suppression impossible')
                            ->body('Des sources de données utilisent cette provenance.')
                            ->danger()
                            ->send();
                        $action->halt();
                    }
                }),
        ];
    }
}
