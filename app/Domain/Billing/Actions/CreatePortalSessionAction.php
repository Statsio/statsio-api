<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Billing\Exceptions\StripeNotConfiguredException;
use App\Models\User\User;
use App\Services\Billing\StripeGateway;

/**
 * Ouvre le portail client Stripe (moyen de paiement, factures, annulation) —
 * voir StripeGateway::createPortalSession().
 */
class CreatePortalSessionAction
{
    public function __construct(private readonly StripeGateway $stripe) {}

    public function execute(User $user): string
    {
        if (! $this->stripe->isConfigured() || blank($user->stripe_customer_id)) {
            throw new StripeNotConfiguredException;
        }

        $frontendUrl = rtrim((string) config('app.frontend_url'), '/');

        return $this->stripe->createPortalSession($user, "{$frontendUrl}/user/parametres");
    }
}
