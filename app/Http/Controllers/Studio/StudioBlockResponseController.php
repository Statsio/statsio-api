<?php

namespace App\Http\Controllers\Studio;

use App\Http\Controllers\Controller;
use App\Models\Studio\StudioBlockResponse;
use App\Models\StudioContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class StudioBlockResponseController extends Controller
{
    private const FORM_TYPES = ['choice', 'checkboxes', 'dropdown', 'scale', 'rating'];

    public function show(Request $request, string $slug, string $blockId): JsonResponse
    {
        $content = $this->findPublished($slug);
        $block = $this->findBlock($content, $blockId);

        $token = (string) $request->query('respondent_token', '');
        $mine = $token !== ''
            ? StudioBlockResponse::where('block_id', $blockId)->where('respondent_token', $token)->first()
            : null;

        return response()->json([
            'success' => true,
            'data' => [
                'answered' => $mine !== null,
                'my_answer' => $mine?->answer['value'] ?? null,
                'aggregate' => $this->aggregateWithDemographics($request, $content, $blockId, $block['type']),
            ],
        ]);
    }

    public function store(Request $request, string $slug, string $blockId): JsonResponse
    {
        $content = $this->findPublished($slug);
        $block = $this->findBlock($content, $blockId);

        if ($content->response_deadline && now()->greaterThan($content->response_deadline)) {
            throw ValidationException::withMessages(['block_id' => ["Ce sondage n'accepte plus de réponses."]]);
        }

        $data = $request->validate([
            'respondent_token' => ['required', 'string', 'max:100'],
            'value' => ['required'],
        ]);

        $value = $this->normalizeValue($block['type'], $data['value']);

        $response = StudioBlockResponse::updateOrCreate(
            ['block_id' => $blockId, 'respondent_token' => $data['respondent_token']],
            [
                'studio_content_id' => $content->id,
                'user_id' => $request->user('sanctum')?->id,
                'answer' => ['value' => $value],
            ],
        );

        return response()->json([
            'success' => true,
            'data' => [
                'answered' => true,
                'my_answer' => $response->answer['value'],
                'aggregate' => $this->aggregateWithDemographics($request, $content, $blockId, $block['type']),
            ],
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    /**
     * Ajoute la répartition démographique des répondants (âge/sexe/profession/région) à
     * l'agrégat — verrou côté serveur, pas seulement un flou cosmétique côté front. Chaque
     * dimension se débloque indépendamment dès que le visiteur a renseigné le champ
     * correspondant sur son propre profil (pas besoin d'un profil 100% complet) : un
     * visiteur qui n'a renseigné que son sexe voit uniquement "Répartition par sexe".
     */
    private function aggregateWithDemographics(Request $request, StudioContent $content, string $blockId, string $type): array
    {
        $aggregate = $this->aggregate($content, $blockId, $type);

        $viewerProfile = $request->user('sanctum')?->profile;
        $unlockedDimensions = array_keys(array_filter([
            'age' => (bool) $viewerProfile?->age_range_id,
            'gender' => (bool) $viewerProfile?->gender_id,
            'profession' => (bool) $viewerProfile?->socio_professional_category_id,
            'region' => (bool) $viewerProfile?->region,
        ]));

        if ($unlockedDimensions !== []) {
            $aggregate['demographics'] = array_intersect_key(
                $this->demographics($content, $blockId),
                array_flip($unlockedDimensions),
            );
        }

        return $aggregate;
    }

    /** @return array<string, array<int, array{label: string, count: int, percent: float}>> */
    private function demographics(StudioContent $content, string $blockId): array
    {
        $profiles = StudioBlockResponse::where('studio_content_id', $content->id)
            ->where('block_id', $blockId)
            ->whereNotNull('user_id')
            ->with(['user.profile.gender', 'user.profile.ageRange', 'user.profile.socioProfessionalCategory'])
            ->get()
            ->map(fn (StudioBlockResponse $r) => $r->user?->profile)
            ->filter();

        return [
            // key + label : le front traduit en français par clé (app/lib/profile-labels.ts) plutôt que
            // de dépendre du label anglais générique seedé côté back (voir database/seeders/UserProfile/*Seeder.php).
            'age' => $this->bucketBy($profiles, fn ($p) => $p->ageRange ? [$p->ageRange->key, $p->ageRange->label] : null),
            'gender' => $this->bucketBy($profiles, fn ($p) => $p->gender ? [$p->gender->key, $p->gender->label] : null),
            'profession' => $this->bucketBy($profiles, fn ($p) => $p->socioProfessionalCategory ? [$p->socioProfessionalCategory->key, $p->socioProfessionalCategory->label] : null),
            // La région est un champ libre non traduit : key === label.
            'region' => $this->bucketBy($profiles, fn ($p) => $p->region ? [$p->region, $p->region] : null),
        ];
    }

    /** @return array<int, array{key: string, label: string, count: int, percent: float}> */
    private function bucketBy(\Illuminate\Support\Collection $profiles, \Closure $resolver): array
    {
        /** @var \Illuminate\Support\Collection<int, array{0: string, 1: string}> $pairs */
        $pairs = $profiles->map($resolver)->filter(fn ($v) => $v !== null);
        $total = $pairs->count();

        if ($total === 0) {
            return [];
        }

        return $pairs->groupBy(fn (array $pair) => $pair[0])
            ->map(fn (\Illuminate\Support\Collection $group, string $key) => [
                'key' => $key,
                'label' => (string) $group->first()[1],
                'count' => $group->count(),
                'percent' => round(($group->count() / $total) * 100, 1),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    private function findPublished(string $slug): StudioContent
    {
        return StudioContent::where('status', 'published')
            ->where(function ($q) use ($slug) {
                $q->where('slug', $slug);
                if (is_numeric($slug)) {
                    $q->orWhere('id', (int) $slug);
                }
            })
            ->firstOrFail();
    }

    private function findBlock(StudioContent $content, string $blockId): array
    {
        foreach ($content->blocks ?? [] as $block) {
            if (($block['id'] ?? null) === $blockId) {
                if (! in_array($block['type'] ?? null, self::FORM_TYPES, true)) {
                    throw ValidationException::withMessages(['block_id' => ["Ce bloc n'accepte pas de réponse."]]);
                }

                return $block;
            }
        }

        abort(404, 'Bloc introuvable.');
    }

    private function normalizeValue(string $type, mixed $value): string|array|float
    {
        return match ($type) {
            'choice', 'dropdown' => is_string($value) && $value !== ''
                ? $value
                : throw ValidationException::withMessages(['value' => ['Valeur invalide.']]),
            'checkboxes' => is_array($value) && $value !== []
                ? array_values(array_map('strval', $value))
                : throw ValidationException::withMessages(['value' => ['Valeur invalide.']]),
            'scale', 'rating' => is_numeric($value)
                ? (float) $value
                : throw ValidationException::withMessages(['value' => ['Valeur invalide.']]),
            default => throw ValidationException::withMessages(['value' => ['Type de bloc non supporté.']]),
        };
    }

    /** @return array<string, mixed> */
    private function aggregate(StudioContent $content, string $blockId, string $type): array
    {
        $answers = StudioBlockResponse::where('studio_content_id', $content->id)
            ->where('block_id', $blockId)
            ->pluck('answer')
            ->map(fn ($a) => $a['value'] ?? null)
            ->filter(fn ($v) => $v !== null)
            ->values();

        $total = $answers->count();

        if (in_array($type, ['choice', 'dropdown'], true)) {
            return $this->optionsAggregate($content, $blockId, $answers, $total);
        }

        if ($type === 'checkboxes') {
            return $this->optionsAggregate($content, $blockId, $answers->flatten(), $total);
        }

        // scale / rating
        $average = $total > 0 ? round($answers->avg(), 2) : 0;
        $distribution = $answers
            ->map(fn ($v) => (string) (int) $v)
            ->countBy()
            ->all();

        return [
            'total_responses' => $total,
            'average' => $average,
            'distribution' => $distribution,
        ];
    }

    private function optionsAggregate(StudioContent $content, string $blockId, \Illuminate\Support\Collection $values, int $total): array
    {
        $block = $this->findBlock($content, $blockId);
        $options = $block['config']['formOptions'] ?? [];

        $counts = $values->countBy();

        $result = collect($options)->map(fn (string $opt) => [
            'value' => $opt,
            'count' => $counts->get($opt, 0),
            'percent' => $total > 0 ? round(($counts->get($opt, 0) / $total) * 100, 1) : 0,
        ])->values()->all();

        return [
            'total_responses' => $total,
            'options' => $result,
        ];
    }
}
