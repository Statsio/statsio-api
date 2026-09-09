<?php

namespace App\Models\Billing;

use App\Models\User\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Abonnement Stripe d'un utilisateur (table `billing_subscriptions`, nommée à part des
 * « abonnements » de suivi de chaîne). Alimenté par les webhooks Stripe — voir
 * App\Domain\Billing\Actions\HandleStripeWebhookAction. Une ligne par abonnement Stripe ;
 * conservée (statut `canceled`) après résiliation, pour l'historique.
 */
class Subscription extends Model
{
    protected $table = 'billing_subscriptions';

    /** Statuts Stripe considérés comme donnant accès au Premium (le paiement peut être en cours de relance). */
    public const ACTIVE_STATUSES = ['active', 'trialing', 'past_due'];

    protected $fillable = [
        'user_id',
        'stripe_subscription_id',
        'stripe_price_id',
        'status',
        'current_period_start',
        'current_period_end',
        'cancel_at_period_end',
        'canceled_at',
    ];

    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'canceled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }
}
