<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Exceptions\StripeNotConfiguredException;
use App\Models\Offer;
use App\Models\User\User;
use App\Services\Billing\StripeGateway;

/**
 * Démarre une session Stripe Checkout (abonnement mensuel) pour l'offre Premium active.
 * Redirection hébergée Stripe — voir StripeGateway::createCheckoutSession().
 */
class CreateCheckoutSessionAction
{
    public function __construct(private readonly StripeGateway $stripe) {}

    public function execute(User $user, ?string $offerKey = null): string
    {
        if (! $this->stripe->isConfigured()) {
            throw new StripeNotConfiguredException;
        }

        $query = Offer::active()->whereNotNull('stripe_price_id');
        if ($offerKey !== null) {
            $query->where('key', $offerKey);
        }
        $offer = $query->orderBy('position')->first();

        if ($offer === null) {
            throw new StripeNotConfiguredException;
        }

        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return $this->stripe->createCheckoutSession(
            $user,
            $offer,
            "{$frontendUrl}/user/parametres?checkout=success",
            "{$frontendUrl}/user/parametres?checkout=cancel",
        );
    }
}
