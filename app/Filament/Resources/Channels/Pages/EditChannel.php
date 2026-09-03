<?php

namespace App\Filament\Resources\Channels\Pages;

use App\Domain\Channel\Actions\ChannelAction;
use App\Domain\Content\Enums\SubBrandEnum;
use App\Filament\Resources\Channels\ChannelResource;
use App\Filament\Resources\Channels\Support\ChannelModerationActions;
use App\Models\Channel\Channel;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditChannel extends EditRecord
{
    protected static string $resource = ChannelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make(ChannelModerationActions::make())
                ->label('Modération')
                ->icon('heroicon-o-shield-check')
                ->button(),
            DeleteAction::make()
                ->using(fn (Channel $record): bool => app(ChannelAction::class)->deleteChannel($record)),
        ];
    }

    /**
     * Pré-remplit le formulaire depuis le profil de la chaîne (le formulaire édite
     * ChannelProfile, pas Channel).
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $profile = $this->record->profile;

        return [
            'name' => $profile?->name,
            'handle' => $profile?->handle,
            'description' => $profile?->description,
            'country' => $profile?->country,
            'sub_brand' => $profile?->sub_brand?->value ?? SubBrandEnum::All->value,
            'custom_color_primary' => $profile?->custom_color_primary,
            'custom_color_secondary' => $profile?->custom_color_secondary,
            'categories' => $profile?->categories ?? [],
        ];
    }

    /**
     * Enregistre via l'action métier existante (sync catégories inclus).
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if ($record->profile) {
            app(ChannelAction::class)->updateChannelProfile($record->profile, $data);
        }

        return $record->refresh();
    }
}
