<?php

namespace App\Domain\Billing\Actions;

use App\Models\Billing\Promotion;
use App\Services\Billing\StripeGateway;

/**
 * Synchronise une promotion vers un Coupon + Promotion Code Stripe. Un Coupon Stripe est
 * immuable (montant/durée) : toute modification de la réduction désactive l'ancien code et
 * recrée un Coupon + code. Activer/désactiver sans changer la réduction se limite à basculer
 * `active` sur le Promotion Code existant. Le champ natif « Ajouter un code promo » de
 * Stripe Checkout s'appuie ensuite directement sur ce code — aucune UI custom côté Statsio.
 */
class SyncPromotionToStripeAction
{
    public function __construct(private readonly StripeGateway $stripe) {}

    public function execute(Promotion $promotion, bool $discountChanged): void
    {
        if (! $this->stripe->isConfigured()) {
            return;
        }

        if ($promotion->stripe_coupon_id === null || $discountChanged) {
            if ($promotion->stripe_promotion_code_id !== null) {
                $this->stripe->setPromotionCodeActive($promotion->stripe_promotion_code_id, false);
            }

            $promotion->stripe_coupon_id = $this->stripe->createCoupon($promotion);
            $promotion->stripe_promotion_code_id = $this->stripe->createPromotionCode($promotion->stripe_coupon_id, $promotion);
            $promotion->save();

            return;
        }

        // Réduction inchangée : ne fait que refléter is_active sur le code existant.
        $this->stripe->setPromotionCodeActive($promotion->stripe_promotion_code_id, $promotion->is_active);
    }
}
