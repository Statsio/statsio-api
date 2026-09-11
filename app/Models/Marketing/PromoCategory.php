<?php

namespace App\Models\Marketing;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Catégorie de promotion affichée en "flash" dans le bandeau promo du front
 * (AppPromoBanner.vue), en alternance avec le ticker de tendances : titre sur
 * 3 lignes (richtext) qui reste affiché pendant que les `infos` défilent une
 * à une. Gérée depuis le back-office (ressource « Catégories de promotion »).
 *
 * Sans rapport avec App\Models\Billing\Promotion (codes promo Stripe).
 */
class PromoCategory extends Model
{
    /** Clé de cache de la réponse publique /api/promo-categories. */
    public const CACHE_KEY = 'promo_categories.public';

    protected $fillable = [
        'name',
        'title_line_1',
        'title_line_1_stroke_color',
        'title_line_1_align',
        'title_line_2',
        'title_line_2_stroke_color',
        'title_line_2_align',
        'title_line_3',
        'title_line_3_stroke_color',
        'title_line_3_align',
        'infos',
        'ticker_duration_seconds',
        'info_duration_seconds',
        'is_active',
        'always_visible',
        'position',
    ];

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    protected function casts(): array
    {
        return [
            'infos' => 'array',
            'ticker_duration_seconds' => 'integer',
            'info_duration_seconds' => 'integer',
            'is_active' => 'boolean',
            'always_visible' => 'boolean',
            'position' => 'integer',
        ];
    }

    /** Catégories actives, dans l'ordre de rotation — utilisé par le bandeau promo public. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('position');
    }
}
