<?php

namespace App\Filament\Resources\Offers\Pages;

use App\Domain\Billing\Actions\SyncOfferToStripeAction;
use App\Filament\Resources\Offers\OfferResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Throwable;

class CreateOffer extends CreateRecord
{
    protected static string $resource = OfferResource::class;

    /**
     * Synchronise vers Stripe (Produit + Prix) juste après la création — pas d'abonné
     * existant à ce stade, donc jamais de migration de tarif à ce moment-là.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $offer = parent::handleRecordCreation($data);

        try {
            app(SyncOfferToStripeAction::class)->execute($offer, previousPriceCents: 0);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Offre créée, mais la synchronisation Stripe a échoué')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        return $offer;
    }
}
