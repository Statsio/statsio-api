<?php

namespace App\Filament\Resources\Offers\Pages;

use App\Domain\Billing\Actions\SyncOfferToStripeAction;
use App\Filament\Resources\Offers\OfferResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class EditOffer extends EditRecord
{
    protected static string $resource = OfferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Capture le tarif AVANT la mise à jour (un Prix Stripe est immuable — voir
     * SyncOfferToStripeAction pour la logique hausse/baisse), puis synchronise.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $previousPriceCents = (int) $record->getOriginal('price_cents');

        $record = parent::handleRecordUpdate($record, $data);

        try {
            app(SyncOfferToStripeAction::class)->execute($record, $previousPriceCents);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Offre enregistrée, mais la synchronisation Stripe a échoué')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        return $record;
    }
}
