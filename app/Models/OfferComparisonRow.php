<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class OfferComparisonRow extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(Offer::CACHE_KEY));
        static::deleted(fn () => Cache::forget(Offer::CACHE_KEY));
    }

    protected $fillable = [
        'label',
        'hint',
        'free_value',
        'premium_value',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position');
    }
}
