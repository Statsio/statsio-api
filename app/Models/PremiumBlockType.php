<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Classification premium/freemium des blocs du Studio, gérée depuis le
 * back-office (ressource « Blocs premium »). Une ligne = un type de bloc
 * réservé à l'offre Premium — voir App\Domain\Content\Support\PremiumBlockGate.
 */
class PremiumBlockType extends Model
{
    private const CACHE_KEY = 'premium_block_types';

    private const CACHE_TTL = 300;

    protected $fillable = ['block_type'];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    /** @return list<string> Types de blocs actuellement réservés au Premium. */
    public static function types(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn () => static::query()->pluck('block_type')->all(),
        );
    }
}
