<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Offer extends Model
{
    /** Clé de cache de la réponse publique /api/offers (offres + comparatif). */
    public const CACHE_KEY = 'offers.public';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /**
     * Défaut PHP (pas seulement la colonne DB) : le formulaire Filament n'a pas de champ
     * devise — sans ceci, `$offer->currency` resterait `null` en mémoire juste après
     * `create()` (Eloquent ne relit pas les défauts DB), et SyncOfferToStripeAction lui
     * enverrait `null` comme devise.
     */
    protected $attributes = [
        'currency' => 'EUR',
    ];

    protected $fillable = [
        'key',
        'name',
        'tagline',
        'price_cents',
        'currency',
        'period',
        'cta_label',
        'cta_url',
        'badge_label',
        'is_highlighted',
        'is_active',
        'position',
        'features',
        'stripe_product_id',
        'stripe_price_id',
        'max_channels',
        'max_channel_members',
        'allows_identity_verification',
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'is_highlighted' => 'boolean',
            'is_active' => 'boolean',
            'position' => 'integer',
            'features' => 'array',
            'max_channels' => 'integer',
            'max_channel_members' => 'integer',
            'allows_identity_verification' => 'boolean',
        ];
    }

    /** Offres actives, dans l'ordre d'affichage — utilisé par la page publique /offres. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('position');
    }

    /** Offres gratuites actives (price_cents = 0). */
    public function scopeFree(Builder $query): Builder
    {
        return $query->where('price_cents', 0);
    }

    /** Offres payantes actives (price_cents > 0). */
    public function scopePaid(Builder $query): Builder
    {
        return $query->where('price_cents', '>', 0);
    }
}
