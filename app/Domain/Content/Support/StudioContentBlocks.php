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

    /** @var list<string> */
    public const FORM_TYPES = ['choice', 'checkboxes', 'dropdown', 'scale', 'rating'];

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
     * Blocs du contenu dans l'ordre de lecture : parcours des sections (ordre du
     * tableau `sections`) → colonnes → blocs de la zone `"{sectionId}-{col}"`.
     * Repli : ordre brut du tableau `blocks`.
     *
     * @return list<array<string, mixed>>
     */
    public static function ordered(StudioContent $content): array
    {
        $blocks = array_values(array_filter($content->blocks ?? [], 'is_array'));
        $sections = array_values(array_filter($content->sections ?? [], 'is_array'));
        if (empty($sections)) {
            return $blocks;
        }

        $byZone = [];
        foreach ($blocks as $block) {
            $byZone[$block['zoneId'] ?? ''][] = $block;
        }

        $ordered = [];
        $seen = [];
        foreach ($sections as $section) {
            $sectionId = $section['id'] ?? '';
            for ($col = 0; $col < 4; $col++) {
                foreach ($byZone["{$sectionId}-{$col}"] ?? [] as $block) {
                    $ordered[] = $block;
                    $seen[$block['id'] ?? ''] = true;
                }
            }
        }
        // Blocs orphelins (zone inconnue, enfants de script…) : à la fin, ordre brut.
        foreach ($blocks as $block) {
            if (! isset($seen[$block['id'] ?? ''])) {
                $ordered[] = $block;
            }
        }

        return $ordered;
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
     * Blocs de formulaire (questions), dans l'ordre de lecture.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<array<string, mixed>>
     */
    public static function formBlocks(array $blocks): array
    {
        return array_values(array_filter(
            $blocks,
            fn ($block) => is_array($block) && in_array($block['type'] ?? '', self::FORM_TYPES, true),
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public static function formQuestionCount(array $blocks): int
    {
        return count(self::formBlocks($blocks));
    }

    /**
     * Types de questions présents (choice / checkboxes / …), dédupliqués, ordre de lecture.
     *
     * @param  list<array<string, mixed>>  $blocks
     * @return list<string>
     */
    public static function formQuestionTypes(array $blocks): array
    {
        $types = [];
        foreach (self::formBlocks($blocks) as $block) {
            $type = $block['type'] ?? '';
            if ($type !== '' && ! in_array($type, $types, true)) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * @param  list<array<string, mixed>>  $blocks
     */
    public static function linkedDatasetCount(array $blocks): int
    {
        $ids = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }
            foreach (ContentDatasetSources::blockDatasetIds($block) as $id) {
                $ids[$id] = true;
            }
        }

        return count($ids);
    }
}
