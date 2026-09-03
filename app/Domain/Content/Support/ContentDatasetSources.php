<?php

namespace App\Domain\Content\Support;

use App\Models\DataIngestion\Dataset;
use App\Models\StudioContent;

/**
 * Logique partagée d'agrégation des jeux de données rattachés à des contenus
 * StatsData : un bloc référence un `datasetId`, et les onglets « Sources de
 * données » (dashboard chaîne et dashboard contenu) listent ces datasets
 * dédupliqués, avec leur fraîcheur et les contenus qui les utilisent.
 */
class ContentDatasetSources
{
    /**
     * Parcourt les blocs d'un contenu (racine, pages, sections) et collecte les
     * `datasetId` non vides, sous forme de chaînes dédupliquées.
     *
     * @return list<string>
     */
    public static function extractDatasetIds(StudioContent $content): array
    {
        $blockGroups = [
            $content->blocks ?? [],
            ...array_map(fn ($page) => $page['blocks'] ?? [], $content->pages ?? []),
            ...array_map(fn ($section) => $section['blocks'] ?? [], $content->sections ?? []),
        ];

        $ids = [];
        foreach ($blockGroups as $blocks) {
            if (! is_array($blocks)) {
                continue;
            }
            foreach ($blocks as $block) {
                if (! is_array($block)) {
                    continue;
                }
                foreach (self::blockDatasetIds($block) as $id) {
                    $ids[] = $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Datasets référencés par un bloc : `datasetId` (legacy / source primaire),
     * `sources[].datasetId` (multi-sources) et `joins[].datasetId` (ancien format).
     *
     * @param  array<string, mixed>  $block
     * @return list<string>
     */
    public static function blockDatasetIds(array $block): array
    {
        $ids = [];
        $push = function ($id) use (&$ids) {
            if ($id !== null && $id !== '') {
                $ids[] = (string) $id;
            }
        };

        $push($block['datasetId'] ?? null);
        foreach ($block['sources'] ?? [] as $source) {
            $push(is_array($source) ? ($source['datasetId'] ?? null) : null);
        }
        foreach ($block['joins'] ?? [] as $join) {
            $push(is_array($join) ? ($join['datasetId'] ?? null) : null);
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<array<string, mixed>>  $usedBy
     * @return array<string, mixed>
     */
    public static function formatSource(Dataset $dataset, array $usedBy): array
    {
        $source = $dataset->dataSource;
        $rowCount = $dataset->row_count ?: ($dataset->latestVersion?->row_count ?? 0);

        return [
            'id' => (string) $dataset->id,
            'name' => $dataset->name,
            'type' => $source?->type?->value,
            'source_kind' => $source?->source_kind,
            'origin' => self::resolveOrigin($dataset),
            'row_count' => (int) $rowCount,
            'status' => self::resolveStatus($dataset),
            'last_refreshed_at' => $source?->last_refreshed_at?->toIso8601String(),
            'next_refresh_at' => $source?->next_refresh_at?->toIso8601String(),
            'refresh_frequency' => $source?->refresh_frequency?->value,
            'used_by_count' => count($usedBy),
            'used_by' => $usedBy,
        ];
    }

    /**
     * Payload de fraîcheur d'un dataset — alimente le badge « Mis à jour il y a… »
     * (page publique + cartes de listing). Partagé avec StudioContentController::format().
     *
     * @return array{is_live: bool, last_refreshed_at: ?string, next_refresh_at: ?string, refresh_frequency: ?string}
     */
    public static function freshnessPayload(Dataset $dataset): array
    {
        $source = $dataset->dataSource;

        return [
            'is_live' => (bool) $source?->isLive(),
            'last_refreshed_at' => $source?->last_refreshed_at?->toIso8601String(),
            'next_refresh_at' => $source?->next_refresh_at?->toIso8601String(),
            'refresh_frequency' => $source?->refresh_frequency?->value,
        ];
    }

    /**
     * Choisit la source la plus « parlante » d'un lot pour un badge unique de carte :
     * une source en direct l'emporte ; sinon la source planifiée (cadence ≠ « jamais »)
     * rafraîchie le plus récemment ; sinon `null` — une source figée (« jamais ») ou
     * sans planification n'affiche rien sur les cartes.
     *
     * @param  list<array{is_live: bool, last_refreshed_at: ?string, next_refresh_at: ?string, refresh_frequency: ?string}>  $payloads
     * @return array{is_live: bool, last_refreshed_at: ?string, next_refresh_at: ?string, refresh_frequency: ?string}|null
     */
    public static function pickPrimaryFreshness(array $payloads): ?array
    {
        foreach ($payloads as $payload) {
            if ($payload['is_live'] ?? false) {
                return $payload;
            }
        }

        $scheduled = array_values(array_filter(
            $payloads,
            fn ($p) => in_array($p['refresh_frequency'] ?? null, ['hourly', 'daily', 'weekly', 'monthly', 'yearly'], true)
                && ! empty($p['last_refreshed_at'])
        ));

        if ($scheduled === []) {
            return null;
        }

        usort($scheduled, fn ($a, $b) => strcmp((string) $b['last_refreshed_at'], (string) $a['last_refreshed_at']));

        return $scheduled[0];
    }

    private static function resolveOrigin(Dataset $dataset): ?string
    {
        $source = $dataset->dataSource;
        if (! $source) {
            return null;
        }

        return $source->original_filename
            ?: ($source->api_config['url'] ?? null)
            ?: $source->name;
    }

    /**
     * Statut consolidé dataset + source : `failed` si l'un des deux a échoué,
     * `ready` si le dataset est prêt, `pending` sinon.
     */
    private static function resolveStatus(Dataset $dataset): string
    {
        $datasetStatus = $dataset->status?->value ?? 'pending';
        $sourceStatus = $dataset->dataSource?->status?->value ?? 'ready';

        if ($datasetStatus === 'failed' || $sourceStatus === 'failed') {
            return 'failed';
        }

        return $datasetStatus === 'ready' ? 'ready' : 'pending';
    }
}
