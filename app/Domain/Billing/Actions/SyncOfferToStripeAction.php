<?php

namespace App\Domain\Billing\Actions;

use App\Jobs\MigrateSubscribersToNewPriceJob;
use App\Models\Offer;
use App\Services\Billing\StripeGateway;

/**
 * Synchronise une offre vers un Produit + Prix Stripe après sa sauvegarde en admin.
 * `$previousPriceCents` doit être capturé AVANT la sauvegarde (un Prix Stripe est
 * immuable : tout changement de montant crée un nouveau Prix) :
 *  - Hausse (ou 1re mise en prix) : nouveau Prix, ancien désactivé, abonnés existants
 *    inchangés (ils gardent leur ancien prix jusqu'à résiliation).
 *  - Baisse : nouveau Prix, ancien désactivé, puis migration de tous les abonnés actifs
 *    vers le nouveau prix (job asynchrone, sans facturation intermédiaire).
 *  - Offre gratuite (`price_cents === 0`) : jamais de Produit/Prix Stripe.
 *  - Stripe non configuré : no-op silencieux (offre utilisable manuellement, sans paiement).
 */
class SyncOfferToStripeAction
{
    public function __construct(private readonly StripeGateway $stripe) {}

    public function execute(Offer $offer, int $previousPriceCents): void
    {
        if (! $this->stripe->isConfigured() || $offer->price_cents === 0) {
            return;
        }

        if ($offer->stripe_product_id === null) {
            $offer->stripe_product_id = $this->stripe->createProduct($offer->name, $offer->tagline);
        } else {
            $this->stripe->updateProduct($offer->stripe_product_id, $offer->name, $offer->tagline);
        }

        $priceChanged = $offer->stripe_price_id === null || $previousPriceCents !== $offer->price_cents;

        if ($priceChanged) {
            $previousPriceId = $offer->stripe_price_id;
            $newPriceId = $this->stripe->createMonthlyPrice($offer->stripe_product_id, $offer->price_cents, $offer->currency);
            $offer->stripe_price_id = $newPriceId;

            if ($previousPriceId !== null) {
                $this->stripe->deactivatePrice($previousPriceId);

                if ($offer->price_cents < $previousPriceCents) {
                    MigrateSubscribersToNewPriceJob::dispatch($previousPriceId, $newPriceId);
                }
            }
        }

        // save() (pas saveQuietly()) : on veut que l'event `saved` de Offer invalide
        // le cache public /api/offers, qui doit refléter le nouveau stripe_price_id.
        $offer->save();
    }
}
