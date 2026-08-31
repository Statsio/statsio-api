<?php

namespace App\Domain\Content\Support;

use App\Models\Studio\StudioBlockResponse;
use App\Models\StudioContent;
use Illuminate\Support\Collection;

/**
 * Enrichissement « participation » des cartes de sondage du listing v2.
 *
 * Tout est calculé en **une seule requête** sur `studio_block_responses` pour
 * l'ensemble des contenus de la page (pas d'agrégat par sondage / pas de N+1),
 * puis agrégé en mémoire — les votes ne sont jamais stockés sur le document.
 */
class SurveyListingAggregates
{
    /** @var array<int, array<string, mixed>> */
    private array $byContent = [];

    /**
     * @param  Collection<int, StudioContent>  $contents  déjà chargés avec `blocks`
     */
    public function __construct(Collection $contents)
    {
        $ids = $contents->pluck('id')->all();
        if ($ids === []) {
            return;
        }

        // 1 requête : toutes les réponses des sondages affichés.
        $responses = StudioBlockResponse::query()
            ->whereIn('studio_content_id', $ids)
            ->get(['studio_content_id', 'block_id', 'respondent_token', 'answer'])
            ->groupBy('studio_content_id');

        foreach ($contents as $content) {
            $this->byContent[$content->id] = $this->buildFor(
                $content,
                $responses->get($content->id) ?? collect(),
            );
        }
    }

    /** @return array<string, mixed> */
    public function for(StudioContent $content): array
    {
        return $this->byContent[$content->id] ?? $this->emptyPayload();
    }

    /**
     * Ids des sondages auxquels le visiteur a déjà répondu (toggle « Pas encore participé »).
     *
     * @param  list<int>  $contentIds
     * @return array<int, true>
     */
    public static function participatedContentIds(?int $viewerId, ?string $token, array $contentIds): array
    {
        if ($contentIds === [] || ($viewerId === null && ($token === null || $token === ''))) {
            return [];
        }

        return StudioBlockResponse::query()
            ->whereIn('studio_content_id', $contentIds)
            ->where(function ($q) use ($viewerId, $token) {
                if ($viewerId !== null) {
                    $q->orWhere('user_id', $viewerId);
                }
                if ($token !== null && $token !== '') {
                    $q->orWhere('respondent_token', $token);
                }
            })
            ->distinct()
            ->pluck('studio_content_id')
            ->mapWithKeys(fn ($id) => [(int) $id => true])
            ->all();
    }

    /**
     * @param  Collection<int, StudioBlockResponse>  $responses
     * @return array<string, mixed>
     */
    private function buildFor(StudioContent $content, Collection $responses): array
    {
        $formBlocks = StudioContentBlocks::formBlocks($content->blocks ?? []);

        // Participation = nombre de répondants distincts, toutes questions confondues.
        $responsesCount = $responses->pluck('respondent_token')->filter()->unique()->count();

        $byBlock = $responses->groupBy('block_id');

        $questionPreviews = [];
        foreach ($formBlocks as $block) {
            $preview = $this->blockPreview($block, $byBlock->get($block['id'] ?? '') ?? collect());
            if ($preview !== null) {
                $questionPreviews[] = $preview;
            }
        }

        $primaryOptions = $questionPreviews[0]['rows'] ?? [];

        return [
            'responses_count' => $responsesCount,
            'questions_count' => count($formBlocks),
            'estimated_minutes' => max(1, (int) ceil(count($formBlocks) * 0.5)),
            'question_types' => StudioContentBlocks::formQuestionTypes($content->blocks ?? []),
            'primary_options' => array_map(
                fn (array $row) => ['label' => $row['label'], 'pct' => $row['pct']],
                array_slice($primaryOptions, 0, 3),
            ),
            'question_previews' => $questionPreviews,
        ];
    }

    /**
     * @param  array<string, mixed>  $block
     * @param  Collection<int, StudioBlockResponse>  $answers
     * @return array{type: string, label: string, rows: list<array{label: string, pct: int, count: int}>}|null
     */
    private function blockPreview(array $block, Collection $answers): ?array
    {
        $type = (string) ($block['type'] ?? '');
        $config = is_array($block['config'] ?? null) ? $block['config'] : [];
        $label = trim((string) ($config['title'] ?? '')) ?: 'Question sans titre';

        $values = $answers
            ->map(fn (StudioBlockResponse $r) => $r->answer['value'] ?? null)
            ->filter(fn ($v) => $v !== null);

        if (in_array($type, ['choice', 'dropdown', 'checkboxes'], true)) {
            $flat = $type === 'checkboxes'
                ? $values->flatMap(fn ($v) => is_array($v) ? $v : [$v])
                : $values;
            $total = $flat->count();
            $counts = $flat->countBy();
            $options = array_values(array_filter(
                is_array($config['formOptions'] ?? null) ? $config['formOptions'] : [],
                'is_string',
            ));

            $rows = collect($options)
                ->map(fn (string $opt) => [
                    'label' => $opt,
                    'count' => (int) $counts->get($opt, 0),
                    'pct' => $total > 0 ? (int) round(($counts->get($opt, 0) / $total) * 100) : 0,
                ])
                ->sortByDesc('count')
                ->values()
                ->all();

            return ['type' => $type, 'label' => $label, 'rows' => $rows];
        }

        // scale / rating : répartition par palier entier.
        $buckets = $values->map(fn ($v) => (string) (int) $v)->countBy();
        $total = $values->count();
        $rows = $buckets
            ->map(fn ($count, $key) => [
                'label' => $key,
                'count' => (int) $count,
                'pct' => $total > 0 ? (int) round(($count / $total) * 100) : 0,
            ])
            ->sortKeys()
            ->values()
            ->all();

        return ['type' => $type, 'label' => $label, 'rows' => $rows];
    }

    /** @return array<string, mixed> */
    private function emptyPayload(): array
    {
        return [
            'responses_count' => 0,
            'questions_count' => 0,
            'estimated_minutes' => 1,
            'question_types' => [],
            'primary_options' => [],
            'question_previews' => [],
        ];
    }
}
