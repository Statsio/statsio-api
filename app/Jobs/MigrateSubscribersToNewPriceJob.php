<?php

namespace App\Jobs;

use App\Models\Billing\Subscription;
use App\Services\Billing\StripeGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Déclenché uniquement pour une **baisse** de tarif (voir SyncOfferToStripeAction) :
 * bascule tous les abonnements actifs sur l'ancien Prix vers le nouveau, sans facturation
 * intermédiaire (`proration_behavior: none` — le nouveau montant s'applique à la prochaine
 * échéance). Une hausse ne déclenche jamais ce job : les abonnés existants gardent leur prix.
 */
class MigrateSubscribersToNewPriceJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public readonly string $oldStripePriceId,
        public readonly string $newStripePriceId,
    ) {
        $this->onQueue('ingestion');
    }

    public function handle(StripeGateway $stripe): void
    {
        Subscription::whereIn('status', Subscription::ACTIVE_STATUSES)
            ->where('stripe_price_id', $this->oldStripePriceId)
            ->each(function (Subscription $subscription) use ($stripe): void {
                $stripe->updateSubscriptionPrice($subscription->stripe_subscription_id, $this->newStripePriceId);
                $subscription->update(['stripe_price_id' => $this->newStripePriceId]);
            });
    }
}
