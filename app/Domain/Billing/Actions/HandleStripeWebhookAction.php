<?php

namespace App\Domain\Billing\Actions;

use App\Domain\Content\Support\PremiumLimits;
use App\Models\Billing\Subscription;
use App\Models\Offer;
use App\Models\User\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Stripe\Event;

/**
 * Traite un événement webhook Stripe déjà vérifié (signature) — synchrone et idempotent,
 * même idiome que App\Domain\Identity\Actions\HandleDiditWebhookAction : rejouer le même
 * événement ne change rien (upsert par id Stripe). Aucun job : le traitement est un simple
 * upsert, pas de risque de latence notable.
 */
class HandleStripeWebhookAction
{
    public function execute(Event $event): void
    {
        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event),
            'customer.subscription.created', 'customer.subscription.updated' => $this->handleSubscriptionUpdated($event),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event),
            // Types non gérés : on acquitte quand même (200) pour ne pas faire désactiver
            // le webhook par Stripe après des échecs répétés.
            default => null,
        };
    }

    /** Lie le Customer Stripe à l'utilisateur dès la 1re session Checkout complétée. */
    private function handleCheckoutCompleted(Event $event): void
    {
        $session = $event->data->object;
        $userId = $session->client_reference_id ?? null;
        $customerId = $session->customer ?? null;

        if (! $userId || ! $customerId) {
            return;
        }

        $user = User::find($userId);
        if ($user && blank($user->stripe_customer_id)) {
            $user->forceFill(['stripe_customer_id' => (string) $customerId])->save();
        }
    }

    private function handleSubscriptionUpdated(Event $event): void
    {
        $subscription = $event->data->object;
        $user = User::where('stripe_customer_id', $subscription->customer)->first();

        if (! $user) {
            return;
        }

        DB::transaction(function () use ($subscription, $user) {
            $item = $subscription->items->data[0] ?? null;
            $periodStart = $item->current_period_start ?? $subscription->current_period_start ?? null;
            $periodEnd = $item->current_period_end ?? $subscription->current_period_end ?? null;

            Subscription::updateOrCreate(
                ['stripe_subscription_id' => $subscription->id],
                [
                    'user_id' => $user->id,
                    'stripe_price_id' => $item?->price?->id ?? '',
                    'status' => $subscription->status,
                    'current_period_start' => $periodStart ? Carbon::createFromTimestamp($periodStart) : null,
                    'current_period_end' => $periodEnd ? Carbon::createFromTimestamp($periodEnd) : null,
                    'cancel_at_period_end' => (bool) $subscription->cancel_at_period_end,
                    'canceled_at' => $subscription->canceled_at ? Carbon::createFromTimestamp($subscription->canceled_at) : null,
                ],
            );

            // "past_due" garde le Premium : Stripe relance le paiement pendant plusieurs
            // jours (Smart Retries) avant de marquer l'abonnement "unpaid"/"canceled".
            if (in_array($subscription->status, Subscription::ACTIVE_STATUSES, true)) {
                $priceId = $item?->price?->id;
                $offer = ($priceId !== null ? Offer::where('stripe_price_id', $priceId)->first() : null)
                    ?? PremiumLimits::paidOffer();

                $user->forceFill([
                    'offer_id' => $offer?->id,
                    'premium_until' => $periodEnd ? Carbon::createFromTimestamp($periodEnd) : null,
                ])->save();
            } else {
                $user->forceFill(['offer_id' => PremiumLimits::freeOffer()?->id])->save();
            }
        });
    }

    private function handleSubscriptionDeleted(Event $event): void
    {
        $subscription = $event->data->object;

        Subscription::where('stripe_subscription_id', $subscription->id)->update([
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);

        $user = User::where('stripe_customer_id', $subscription->customer)->first();
        $user?->forceFill(['offer_id' => PremiumLimits::freeOffer()?->id])->save();
    }
}
