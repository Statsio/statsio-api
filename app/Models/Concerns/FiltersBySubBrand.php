<?php

namespace App\Models\Concerns;

use App\Domain\Content\Enums\SubBrandEnum;
use Illuminate\Database\Eloquent\Builder;

/**
 * Pour les modèles portant une colonne `sub_brand` (catégories de contenu, de
 * chaîne…). Fournit un scope de filtrage par sous-marque.
 */
trait FiltersBySubBrand
{
    /**
     * Lignes disponibles pour une sous-marque : celles qui lui sont propres plus
     * celles marquées « toutes les marques ». `null`/vide/`all` = aucun filtre.
     */
    public function scopeForSubBrand(Builder $query, SubBrandEnum|string|null $brand): Builder
    {
        $value = $brand instanceof SubBrandEnum ? $brand->value : $brand;

        if ($value === null || $value === '' || $value === SubBrandEnum::All->value) {
            return $query;
        }

        return $query->whereIn('sub_brand', [$value, SubBrandEnum::All->value]);
    }
}
