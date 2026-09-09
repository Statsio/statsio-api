<?php

namespace App\Services\Billing;

use App\Domain\Billing\Exceptions\StripeNotConfiguredException;
use App\Models\Billing\Promotion;
use App\Models\Offer;
use App\Models\User\User;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Relais vers l'API Stripe (abonnement Premium) — Produits/Prix, Coupons/Codes promo,
 * Customers, Checkout Session, portail client, vérification de signature webhook.
 *
 * Contrairement aux autres intégrations tierces de ce projet (App\Services\Identity\DiditApiClient),
 * on passe par le SDK officiel `stripe/stripe-php` plutôt que par Http:: — la surface d'API
 * Stripe (Customers, Prices, Subscriptions, Coupons, Checkout, Billing Portal, vérification de
 * signature webhook) est trop large pour la réimplémenter à la main sans risque. Cette classe
 * reste le seul point d'entrée du domaine vers Stripe : les tests la mockent (container binding),
 * jamais d'appel réseau réel en CI.
 */
class StripeGateway
{
    private ?StripeClient $client = null;

    public function isConfigured(): bool
    {
        return filled(config('services.stripe.secret'));
    }

    private function client(): StripeClient
    {
        if (! $this->isConfigured()) {
            throw new StripeNotConfiguredException;
        }

        return $this->client ??= new StripeClient((string) config('services.stripe.secret'));
    }

    // ─── Customers ──────────────────────────────────────────────────────────────

    /** Crée (et persiste) le Customer Stripe du compte s'il n'existe pas encore. */
    public function getOrCreateCustomer(User $user): string
    {
        if (filled($user->stripe_customer_id)) {
            return $user->stripe_customer_id;
        }

        $customer = $this->client()->customers->create([
            'email' => $user->email,
            'metadata' => ['user_id' => (string) $user->id],
        ]);

        $user->forceFill(['stripe_customer_id' => $customer->id])->save();

        return $customer->id;
    }

    // ─── Checkout & portail (pages hébergées Stripe) ───────────────────────────

    /** @return string URL de la page Checkout hébergée vers laquelle rediriger le client. */
    public function createCheckoutSession(User $user, Offer $offer, string $successUrl, string $cancelUrl): string
    {
        if (blank($offer->stripe_price_id)) {
            throw new StripeNotConfiguredException;
        }

        $session = $this->client()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $this->getOrCreateCustomer($user),
            'client_reference_id' => (string) $user->id,
            'line_items' => [['price' => $offer->stripe_price_id, 'quantity' => 1]],
            'allow_promotion_codes' => true,
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
        ]);

        return (string) $session->url;
    }

    /** @return string URL du portail client hébergé (moyen de paiement, factures, annulation). */
    public function createPortalSession(User $user, string $returnUrl): string
    {
        $session = $this->client()->billingPortal->sessions->create([
            'customer' => $this->getOrCreateCustomer($user),
            'return_url' => $returnUrl,
        ]);

        return (string) $session->url;
    }

    // ─── Produits & prix (synchronisation des tarifs) ──────────────────────────

    public function createProduct(string $name, ?string $description = null): string
    {
        $product = $this->client()->products->create(array_filter([
            'name' => $name,
            'description' => $description,
        ]));

        return $product->id;
    }

    public function updateProduct(string $productId, string $name, ?string $description = null): void
    {
        $this->client()->products->update($productId, array_filter([
            'name' => $name,
            'description' => $description,
        ]));
    }

    /** Prix récurrent mensuel — un Prix Stripe est immuable, changer le tarif crée toujours un nouveau Prix. */
    public function createMonthlyPrice(string $productId, int $amountCents, string $currency): string
    {
        $price = $this->client()->prices->create([
            'product' => $productId,
            'unit_amount' => $amountCents,
            'currency' => mb_strtolower($currency),
            'recurring' => ['interval' => 'month'],
        ]);

        return $price->id;
    }

    public function deactivatePrice(string $priceId): void
    {
        $this->client()->prices->update($priceId, ['active' => false]);
    }

    /**
     * Bascule un abonnement Stripe vers un nouveau Prix, sans facturation intermédiaire
     * (le nouveau montant s'applique à la prochaine échéance) — utilisé uniquement pour
     * une baisse de tarif migrée à tous les abonnés actifs (voir MigrateSubscribersToNewPriceJob).
     */
    public function updateSubscriptionPrice(string $stripeSubscriptionId, string $newPriceId): void
    {
        $subscription = $this->client()->subscriptions->retrieve($stripeSubscriptionId);
        $itemId = $subscription->items->data[0]->id ?? null;

        if ($itemId === null) {
            return;
        }

        $this->client()->subscriptions->update($stripeSubscriptionId, [
            'items' => [['id' => $itemId, 'price' => $newPriceId]],
            'proration_behavior' => 'none',
        ]);
    }

    // ─── Coupons & codes promo ──────────────────────────────────────────────────

    public function createCoupon(Promotion $promotion): string
    {
        $payload = [
            'duration' => $promotion->duration->value,
        ];

        if ($promotion->type->value === 'percent') {
            $payload['percent_off'] = $promotion->percent_off;
        } else {
            $payload['amount_off'] = $promotion->amount_off_cents;
            $payload['currency'] = mb_strtolower($promotion->currency);
        }

        if ($promotion->duration->value === 'repeating') {
            $payload['duration_in_months'] = $promotion->duration_in_months;
        }

        $coupon = $this->client()->coupons->create($payload);

        return $coupon->id;
    }

    public function createPromotionCode(string $couponId, Promotion $promotion): string
    {
        $promotionCode = $this->client()->promotionCodes->create(array_filter([
            'coupon' => $couponId,
            'code' => $promotion->code,
            'active' => $promotion->is_active,
            'max_redemptions' => $promotion->max_redemptions,
            'expires_at' => $promotion->expires_at?->getTimestamp(),
        ], fn ($v) => $v !== null));

        return $promotionCode->id;
    }

    public function setPromotionCodeActive(string $promotionCodeId, bool $active): void
    {
        $this->client()->promotionCodes->update($promotionCodeId, ['active' => $active]);
    }

    // ─── Webhooks ───────────────────────────────────────────────────────────────

    /**
     * @throws SignatureVerificationException Signature invalide — la contrôleur répond 400.
     */
    public function constructWebhookEvent(string $payload, string $signatureHeader): Event
    {
        return Webhook::constructEvent($payload, $signatureHeader, (string) config('services.stripe.webhook_secret'));
    }
}
