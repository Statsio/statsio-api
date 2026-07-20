<?php

namespace App\Services\Medicaments;

use App\Models\Medicaments\MedicamentSalesStat;
use Illuminate\Support\Facades\Cache;

/**
 * Stats de ventes/volumes (Open Medic) importées dans `medicament_sales_stats` par
 * `medicaments:import-open-medic` — pas d'appel réseau ici, uniquement des agrégats DB,
 * mais mis en cache tout de même car `getTopSoldMedicaments` scanne toute la table.
 */
class MedicamentSalesService
{
    private const CACHE_TTL_MINUTES = 60;

    /**
     * Un médicament (CIS) a en général plusieurs présentations (CIP13) — on additionne les
     * boîtes de toutes ses présentations année par année pour obtenir la tendance du médicament.
     *
     * @param  string[]  $cip13Codes
     * @return array{value: int, year: int, trend: array<array{year: int, value: int}>}|null null si Open Medic n'a aucune donnée pour ces CIP13 (médicament non remboursé/non suivi).
     */
    public function getTrendForCip13Codes(array $cip13Codes): ?array
    {
        $cip13Codes = array_values(array_filter($cip13Codes));
        if (empty($cip13Codes)) {
            return null;
        }

        $cacheKey = 'medicament-sales:trend:'.md5(implode(',', $cip13Codes));

        $trend = Cache::remember($cacheKey, now()->addMinutes(self::CACHE_TTL_MINUTES), fn () => MedicamentSalesStat::query()
            ->whereIn('cip13', $cip13Codes)
            ->selectRaw('year, SUM(boxes_delivered) as total')
            ->groupBy('year')
            ->orderBy('year')
            ->get()
            ->map(fn ($row) => ['year' => (int) $row->year, 'value' => (int) $row->total])
            ->values()
            ->all());

        if (empty($trend)) {
            return null;
        }

        $latest = end($trend);

        return [
            'value' => $latest['value'],
            'year' => $latest['year'],
            'trend' => $trend,
        ];
    }

    /** @return array<int, array{cip13: string, label: ?string, boxes: int}> */
    public function getTopSoldMedicaments(int $limit = 10): array
    {
        return Cache::remember("medicament-sales:top:{$limit}", now()->addMinutes(self::CACHE_TTL_MINUTES), function () use ($limit) {
            $latestYear = MedicamentSalesStat::max('year');
            if ($latestYear === null) {
                return [];
            }

            return MedicamentSalesStat::query()
                ->where('year', $latestYear)
                ->orderByDesc('boxes_delivered')
                ->limit($limit)
                ->get(['cip13', 'label', 'boxes_delivered'])
                ->map(fn (MedicamentSalesStat $stat) => [
                    'cip13' => $stat->cip13,
                    'label' => $stat->label,
                    'boxes' => $stat->boxes_delivered,
                ])
                ->all();
        });
    }
}
