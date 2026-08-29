<?php

namespace App\Domain\Content\Actions;

use App\Domain\Content\Support\ContentDatasetSources;
use App\Models\DataIngestion\Dataset;
use App\Models\StudioContent;

/**
 * Agrège les jeux de données rattachés à un contenu unique : chaque bloc
 * StatsData référence un `datasetId`, et l'onglet « Sources de données » du
 * dashboard du contenu liste ces datasets (dédupliqués) avec leur fraîcheur.
 */
class StudioContentDataSourcesAction
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDataSources(StudioContent $content): array
    {
        $datasetIds = ContentDatasetSources::extractDatasetIds($content);

        if (empty($datasetIds)) {
            return [];
        }

        // Forme identique à l'onglet chaîne : `used_by` contient le contenu courant
        // pour que le front puisse réutiliser le même composant de ligne.
        $usedBy = [[
            'id' => (string) $content->id,
            'title' => $content->title,
            'type' => $content->type ?? 'statsdata',
            'slug' => $content->slug,
        ]];

        return Dataset::query()
            ->with(['dataSource', 'latestVersion'])
            ->whereIn('id', $datasetIds)
            ->get()
            ->map(fn (Dataset $dataset) => ContentDatasetSources::formatSource($dataset, $usedBy))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
