<?php

namespace App\Domain\Billing\Exceptions;

use Exception;

/**
 * Levée quand une action nécessitant Stripe (checkout, portail, sync de tarif) est
 * tentée alors que `STRIPE_SECRET` n'est pas renseigné — voir StripeGateway::isConfigured().
 */
class StripeNotConfiguredException extends Exception
{
    public function __construct()
    {
        parent::__construct(__('errors.stripe_not_configured'));
    }
}
