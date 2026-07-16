<?php

namespace App\Services\Soins;

/**
 * Registre pur des catégories "soins" affichées sur la fiche pays (section système de santé) —
 * aucune logique ici, uniquement de la configuration (voir CountrySoinsService pour le fetch/
 * l'agrégation). Mirror de TrackedDiseases : chaque métrique porte son code indicateur GHO,
 * vérifié individuellement contre https://ghoapi.azureedge.net/api/Indicator avant d'être ajouté
 * ici. `scale` convertit les indicateurs GHO publiés "pour 10 000 hab." vers un affichage
 * "pour 1000 hab." (×0.1), comme déjà fait dans PaysController::INDICATORS pour les médecins.
 */
class TrackedSoinsCategories
{
    private const CATEGORIES = [
        'workforce' => [
            'label' => 'Ressources humaines',
            'color' => '#991b1b',
            'tint' => '#fef2f2',
            'primary' => 'physicians',
            'rankUnit' => '/1000',
            'hasTrend' => true,
            'trendTitle' => 'Médecins pour 1000 hab. — évolution',
            'metrics' => [
                'physicians' => ['label' => 'Médecins', 'unit' => '/1000 hab.', 'decimals' => 1, 'indicatorCode' => 'HWF_0001', 'scale' => 0.1],
                'nurses' => ['label' => 'Infirmiers et sages-femmes', 'unit' => '/1000 hab.', 'decimals' => 1, 'indicatorCode' => 'HWF_0006', 'scale' => 0.1],
                'dentists' => ['label' => 'Dentistes', 'unit' => '/1000 hab.', 'decimals' => 2, 'indicatorCode' => 'HWF_0010', 'scale' => 0.1],
                'pharmacists' => ['label' => 'Pharmaciens', 'unit' => '/1000 hab.', 'decimals' => 2, 'indicatorCode' => 'HWF_0014', 'scale' => 0.1],
            ],
        ],
        'infra' => [
            'label' => 'Infrastructures',
            'color' => '#b45309',
            'tint' => '#fffbeb',
            'primary' => 'bedsPer1000',
            'rankUnit' => '/1000',
            'hasTrend' => false,
            'metrics' => [
                'bedsPer1000' => ['label' => "Lits d'hôpital", 'unit' => '/1000 hab.', 'decimals' => 1, 'indicatorCode' => 'WHS6_102', 'scale' => 0.1],
            ],
        ],
        'financing' => [
            'label' => 'Financement',
            'color' => '#7e22ce',
            'tint' => '#faf5ff',
            'primary' => 'healthExpGDP',
            'rankUnit' => '% PIB',
            'hasTrend' => false,
            'metrics' => [
                'healthExpGDP' => ['label' => 'Dépenses totales de santé', 'unit' => '% PIB', 'decimals' => 1, 'indicatorCode' => 'GHED_CHEGDP_SHA2011', 'scale' => 1],
                'perCapitaSpend' => ['label' => 'Dépenses par habitant', 'unit' => '$', 'decimals' => 0, 'indicatorCode' => 'GHED_CHE_pc_US_SHA2011', 'scale' => 1],
                'publicExpShare' => ['label' => 'Part des dépenses publiques', 'unit' => '% du total', 'decimals' => 0, 'indicatorCode' => 'GHED_GGHE-DCHE_SHA2011', 'scale' => 1],
            ],
        ],
        'access' => [
            'label' => 'Couverture sanitaire',
            'color' => '#0e7490',
            'tint' => '#ecfeff',
            'primary' => 'uhcIndex',
            'rankUnit' => '/100',
            'hasTrend' => false,
            'metrics' => [
                'uhcIndex' => ['label' => 'Couverture sanitaire universelle (UHC)', 'unit' => '/100', 'decimals' => 0, 'indicatorCode' => 'UHC_INDEX_REPORTED', 'scale' => 1],
            ],
        ],
        'vaccination' => [
            'label' => 'Vaccination',
            'color' => '#be123c',
            'tint' => '#fff1f2',
            'primary' => 'measlesCoverage',
            'rankUnit' => '%',
            'hasTrend' => true,
            'trendTitle' => 'Couverture vaccinale rougeole (MCV1) — évolution',
            'metrics' => [
                'measlesCoverage' => ['label' => 'Couverture vaccinale rougeole (MCV1)', 'unit' => '%', 'decimals' => 0, 'indicatorCode' => 'WHS8_110', 'scale' => 1],
            ],
        ],
    ];

    /** @return array<string, array{label: string, color: string, tint: string, primary: string, rankUnit: string, hasTrend: bool, trendTitle?: string, metrics: array<string, array{label: string, unit: string, decimals: int, indicatorCode: ?string, scale: float}>}> */
    public static function categories(): array
    {
        return self::CATEGORIES;
    }

    public static function find(string $id): ?array
    {
        return self::CATEGORIES[$id] ?? null;
    }
}
