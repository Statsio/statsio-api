<?php

namespace App\Domain\Content\Support;

use App\Models\StudioContent;

/**
 * Collecte les blocs d'un contenu (racine, pages, sections) sans hydrater
 * les datasets — pour les listes publiques (temps de lecture, graphiques, sources).
 */
class StudioContentBlocks
{
    /** @var list<string> */
    public const TEXT_TYPES = ['heading', 'paragraph', 'quote', 'callout', 'retenir'];

    /** @var list<string> */
    public const CHART_TYPES = ['bar', 'line', 'pie', 'map'];

    /**
     * @return list<array<string, mixed>>
     */
    public static function all(StudioContent $content): array
    {
        $groups = [
            $content->blocks ?? [],
            ...array_map(fn ($page) => $page['blocks'] ?? [], $content->pages ?? []),
            ...array_map(fn ($section) => $section['blocks'] ?? [], $content->sections ?? []),
        ];

        $out = [];
        foreach ($groups as $blocks) {
            if (! is_array($blocks)) {
                continue;
            }
            foreach ($blocks as $block) {
                if (is_array($block)) {
                    $out[] = $block;
                }
            }
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public static function readingMinutes(array $blocks): int
    {
        $text = '';
        foreach ($blocks as $block) {
            $type = $block['type'] ?? '';
            if (! in_array($type, self::TEXT_TYPES, true)) {
                continue;
            }
            $config = is_array($block['config'] ?? null) ? $block['config'] : [];
            foreach (['text', 'html', 'content', 'body', 'quote'] as $key) {
                if (! empty($config[$key]) && is_string($config[$key])) {
                    $text .= ' '.$config[$key];
                }
            }
        }

        $plain = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($text))) ?? '');
        if ($plain === '') {
            return 1;
        }

        $words = count(preg_split('/\s+/', $plain) ?: []);

        return max(1, (int) ceil($words / 180));
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public static function chartCount(array $blocks): int
    {
        $n = 0;
        foreach ($blocks as $block) {
            if (in_array($block['type'] ?? '', self::CHART_TYPES, true)) {
                $n++;
            }
        }

        return $n;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public static function linkedDatasetCount(array $blocks): int
    {
        $ids = [];
        foreach ($blocks as $block) {
            $datasetId = $block['datasetId'] ?? null;
            if ($datasetId !== null && $datasetId !== '') {
                $ids[(string) $datasetId] = true;
            }
        }

        return count($ids);
    }
}
