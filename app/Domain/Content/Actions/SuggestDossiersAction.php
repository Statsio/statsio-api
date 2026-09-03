<?php

namespace App\Domain\Content\Actions;

use App\Domain\Content\Support\StudioContentListing;
use App\Models\Content\Dossier;
use Illuminate\Support\Collection;

/**
 * Suggère les dossiers éditoriaux pertinents pour un contenu, à partir de son
 * titre (correspondance de mots-clés) et, en appoint, des catégories déjà
 * sélectionnées (recoupement). Purement déterministe, sans appel externe.
 */
class SuggestDossiersAction
{
    /** Poids d'une correspondance de phrase multi-mots (nom / mot-clé) dans le titre. */
    private const PHRASE_WEIGHT = 5;

    /** Poids d'une correspondance de token simple dans le titre. */
    private const TOKEN_WEIGHT = 3;

    /** Poids par catégorie commune entre le dossier et le contenu. */
    private const CATEGORY_WEIGHT = 4;

    private const MIN_TOKEN_LENGTH = 3;

    /** Mots vides français ignorés lors du découpage du titre. */
    private const STOPWORDS = [
        'le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'et', 'en', 'au', 'aux',
        'pour', 'par', 'sur', 'dans', 'avec', 'sans', 'ses', 'son', 'sa', 'que',
        'qui', 'quoi', 'dont', 'est', 'sont', 'plus', 'moins', 'entre', 'vers',
        'chez', 'ceci', 'cela', 'leur', 'leurs', 'nos', 'vos',
    ];

    /**
     * @param  list<string>  $categories  tableau `categories` du contenu (catégories topicales + format)
     * @return Collection<int, Dossier>
     */
    public function execute(string $title, array $categories = [], int $limit = 5): Collection
    {
        $titleNormalized = StudioContentListing::normalizeKey($title);
        $titleTokens = $this->tokenize($title);

        if ($titleTokens === [] && $titleNormalized === '') {
            return collect();
        }

        // Catégories topicales du contenu (on retire les formats éditoriaux).
        $contentCategoryKeys = collect($categories)
            ->filter(fn ($c) => is_string($c) && $c !== '')
            ->map(fn ($c) => StudioContentListing::normalizeKey($c))
            ->reject(fn ($k) => in_array($k, StudioContentListing::FORMATS, true))
            ->unique()
            ->values();

        return Dossier::active()
            ->with('contentCategories:id,slug')
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(function (Dossier $dossier) use ($titleNormalized, $titleTokens, $contentCategoryKeys) {
                $score = $this->titleScore($dossier, $titleNormalized, $titleTokens);
                $matchedTitle = $score > 0;

                $dossierCategoryKeys = $dossier->contentCategories
                    ->pluck('slug')
                    ->map(fn ($s) => StudioContentListing::normalizeKey((string) $s));
                $commonCategories = $dossierCategoryKeys->intersect($contentCategoryKeys)->count();
                $score += $commonCategories * self::CATEGORY_WEIGHT;

                return ['dossier' => $dossier, 'score' => $score, 'matched_title' => $matchedTitle];
            })
            // On ne propose un dossier que s'il « accroche » le titre.
            ->filter(fn ($row) => $row['matched_title'])
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('dossier')
            ->values();
    }

    /**
     * @param  list<string>  $titleTokens
     */
    private function titleScore(Dossier $dossier, string $titleNormalized, array $titleTokens): int
    {
        $terms = collect([$dossier->name])
            ->merge(is_array($dossier->keywords) ? $dossier->keywords : [])
            ->filter(fn ($t) => is_string($t) && trim($t) !== '');

        $score = 0;

        foreach ($terms as $term) {
            $termNormalized = StudioContentListing::normalizeKey($term);
            if ($termNormalized === '') {
                continue;
            }

            $termTokens = $this->tokenize($term);

            // Phrase multi-mots présente telle quelle dans le titre normalisé.
            if (count($termTokens) > 1 && $termNormalized !== '' && str_contains($titleNormalized, $termNormalized)) {
                $score += self::PHRASE_WEIGHT;

                continue;
            }

            foreach ($termTokens as $token) {
                if (in_array($token, $titleTokens, true)) {
                    $score += self::TOKEN_WEIGHT;
                }
            }
        }

        return $score;
    }

    /**
     * Découpe une chaîne en tokens repliés ASCII (minuscules, sans accent),
     * longueur ≥ 3, hors mots vides.
     *
     * @return list<string>
     */
    private function tokenize(string $value): array
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $folded = strtolower($ascii !== false && $ascii !== '' ? $ascii : $value);
        $parts = preg_split('/[^a-z0-9]+/', $folded, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $parts,
            fn ($t) => strlen($t) >= self::MIN_TOKEN_LENGTH && ! in_array($t, self::STOPWORDS, true),
        )));
    }
}
