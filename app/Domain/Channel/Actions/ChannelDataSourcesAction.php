<?php

namespace App\Domain\Channel\Actions;

use App\Domain\Content\Support\ContentDatasetSources;
use App\Models\Channel\Channel;
use App\Models\DataIngestion\Dataset;
use App\Models\StudioContent;

/**
 * Agrège les jeux de données rattachés aux contenus publiés au nom d'une chaîne :
 * chaque bloc StatsData référence un `datasetId`, et l'onglet « Sources de
 * données » du dashboard chaîne liste ces datasets (dédupliqués) avec leur
 * fraîcheur et les contenus qui les utilisent.
 */
class ChannelDataSourcesAction
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function getDataSources(Channel $channel): array
    {
        $contents = StudioContent::query()
            ->where('channel_id', $channel->id)
            ->where('published_as', 'channel')
            ->get(['id', 'title', 'type', 'slug', 'blocks', 'pages', 'sections']);

        // datasetId (string) => liste des contenus qui l'utilisent
        $usage = [];
        foreach ($contents as $content) {
            foreach (ContentDatasetSources::extractDatasetIds($content) as $datasetId) {
                $usage[$datasetId] ??= [];
                $usage[$datasetId][$content->id] = [
                    'id' => (string) $content->id,
                    'title' => $content->title,
                    'type' => $content->type ?? 'statsdata',
                    'slug' => $content->slug,
                ];
            }
        }

        if (empty($usage)) {
            return [];
        }

        $datasets = Dataset::query()
            ->with(['dataSource', 'latestVersion'])
            ->whereIn('id', array_keys($usage))
            ->get();

        return $datasets
            ->map(fn (Dataset $dataset) => ContentDatasetSources::formatSource(
                $dataset,
                array_values($usage[(string) $dataset->id] ?? []),
            ))
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }
}
