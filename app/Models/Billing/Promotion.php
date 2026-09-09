<?php

namespace App\Models\Billing;

use App\Domain\Billing\Enums\PromotionDurationEnum;
use App\Domain\Billing\Enums\PromotionTypeEnum;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Code promo de l'offre Premium, synchronisé vers un Coupon + Promotion Code Stripe
 * (voir App\Domain\Billing\Actions\SyncPromotionToStripeAction). Appliqué par le client
 * via le champ natif « Ajouter un code promo » de Stripe Checkout.
 */
class Promotion extends Model
{
    protected $fillable = [
        'code',
        'type',
        'percent_off',
        'amount_off_cents',
        'currency',
        'duration',
        'duration_in_months',
        'max_redemptions',
        'expires_at',
        'is_active',
        'stripe_coupon_id',
        'stripe_promotion_code_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => PromotionTypeEnum::class,
            'duration' => PromotionDurationEnum::class,
            'percent_off' => 'integer',
            'amount_off_cents' => 'integer',
            'duration_in_months' => 'integer',
            'max_redemptions' => 'integer',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
