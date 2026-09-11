<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Classification premium/freemium des blocs du Studio, gérée depuis le
 * back-office (ressource « Blocs premium »). Une ligne = un type de bloc
 * réservé à une offre réelle (`offer_id`) — voir
 * App\Domain\Content\Support\PremiumBlockGate.
 */
class PremiumBlockType extends Model
{
    private const CACHE_KEY = 'premium_block_types';

    private const OFFERS_CACHE_KEY = 'premium_block_types.offers';

    private const CACHE_TTL = 300;

    protected $fillable = ['block_type', 'offer_id'];

    protected static function booted(): void
    {
        static::saved(function (): void {
            Cache::forget(self::CACHE_KEY);
            Cache::forget(self::OFFERS_CACHE_KEY);
        });
        static::deleted(function (): void {
            Cache::forget(self::CACHE_KEY);
            Cache::forget(self::OFFERS_CACHE_KEY);
        });
    }

    /** Offre qui débloque ce type de bloc. */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    /** @return list<string> Types de blocs actuellement réservés (à une offre payante). */
    public static function types(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn () => static::query()->pluck('block_type')->all(),
        );
    }

    /**
     * Offre requise par type de bloc — pour l'affichage front (nom réel de l'offre au
     * lieu d'un libellé « Premium » codé en dur). N'inclut que les blocs dont la ligne
     * a effectivement une offre liée (défense en profondeur si `offer_id` est resté vide).
     *
     * @return array<string, array{id: int, key: string, name: string}>
     */
    public static function offersByType(): array
    {
        return Cache::remember(
            self::OFFERS_CACHE_KEY,
            self::CACHE_TTL,
            fn () => static::query()->with('offer')->get()
                ->filter(fn (self $row) => $row->offer !== null)
                ->mapWithKeys(fn (self $row) => [
                    $row->block_type => [
                        'id' => $row->offer->id,
                        'key' => $row->offer->key,
                        'name' => $row->offer->name,
                    ],
                ])
                ->all(),
        );
    }
}
