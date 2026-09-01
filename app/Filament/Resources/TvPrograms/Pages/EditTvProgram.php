<?php

namespace App\Filament\Resources\TvPrograms\Pages;

use App\Filament\Resources\TvPrograms\TvProgramResource;
use App\Models\Tv\TvProgram;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTvProgram extends EditRecord
{
    protected static string $resource = TvProgramResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function (TvProgram $record): void {
                    // Cascade identique à l'ancien AdminProgramController::destroy
                    $record->broadcasts()->each(function ($broadcast): void {
                        $broadcast->audience()->delete();
                        $broadcast->userViews()->delete();
                        $broadcast->delete();
                    });
                    $record->delete();
                }),
        ];
    }
}
