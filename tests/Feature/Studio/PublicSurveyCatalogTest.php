<?php

namespace Tests\Feature\Studio;

use App\Models\Studio\StudioBlockResponse;
use App\Models\StudioContent;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicSurveyCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    private function survey(string $kind, array $attrs = []): StudioContent
    {
        return StudioContentFactory::new()->published()->create(array_merge([
            'user_id' => $this->user->id,
            'type' => 'survey',
            'survey_kind' => $kind,
            'title' => ucfirst($kind).' '.fake()->unique()->word(),
        ], $attrs));
    }

    public function test_catalog_exposes_survey_fields_and_participation(): void
    {
        $poll = $this->survey('single_question', [
            'title' => 'Le télétravail doit-il rester la norme ?',
            'requires_identity_verification' => true,
            'blocks' => [[
                'id' => 'q1',
                'type' => 'choice',
                'config' => ['title' => 'Votre avis ?', 'formOptions' => ['Oui', 'Non', 'Selon les métiers']],
            ]],
        ]);

        foreach (['Oui', 'Oui', 'Non'] as $i => $value) {
            StudioBlockResponse::factory()->create([
                'studio_content_id' => $poll->id,
                'block_id' => 'q1',
                'respondent_token' => "tok-$i",
                'answer' => ['value' => $value],
            ]);
        }

        $response = $this->getJson('/api/studio/content/public/catalog?type=survey');

        $response->assertOk()
            ->assertJsonPath('data.0.id', (string) $poll->id)
            ->assertJsonPath('data.0.survey_kind', 'single_question')
            ->assertJsonPath('data.0.requires_identity_verification', true)
            ->assertJsonPath('data.0.responses_count', 3)
            ->assertJsonPath('data.0.questions_count', 1)
            ->assertJsonPath('data.0.primary_options.0.label', 'Oui')
            ->assertJsonPath('data.0.primary_options.0.pct', 67);
    }

    public function test_catalog_filters_by_survey_kind_and_status(): void
    {
        $this->survey('single_question');
        $petition = $this->survey('petition');
        $closed = $this->survey('long', ['response_deadline' => now()->subDay()]);

        $this->getJson('/api/studio/content/public/catalog?type=survey&survey_kind=petition')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', (string) $petition->id);

        $this->getJson('/api/studio/content/public/catalog?type=survey&status=clos')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', (string) $closed->id);

        $this->getJson('/api/studio/content/public/catalog?type=survey&status=ouvert')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_catalog_returns_survey_kind_facets_and_petition_stat(): void
    {
        $this->survey('petition');
        $this->survey('petition');
        $this->survey('long');

        $response = $this->getJson('/api/studio/content/public/catalog?type=survey');

        $response->assertOk()
            ->assertJsonPath('stats.charts', 2) // pétitions actives
            ->assertJsonPath('facets.survey_kinds.0.value', '')
            ->assertJsonPath('facets.survey_kinds.0.count', 3);
    }

    public function test_catalog_can_sort_by_number_of_responses(): void
    {
        $quiet = $this->survey('single_question', ['title' => 'Peu suivi']);
        $popular = $this->survey('single_question', ['title' => 'Très suivi']);

        StudioBlockResponse::factory()->create(['studio_content_id' => $quiet->id, 'block_id' => 'a']);
        foreach (range(1, 4) as $i) {
            StudioBlockResponse::factory()->create([
                'studio_content_id' => $popular->id,
                'block_id' => 'b',
                'respondent_token' => "t$i",
            ]);
        }

        $this->getJson('/api/studio/content/public/catalog?type=survey&sort=votes')
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $popular->id)
            ->assertJsonPath('data.1.id', (string) $quiet->id);
    }

    public function test_not_participated_filter_excludes_answered_surveys(): void
    {
        $answered = $this->survey('single_question');
        $fresh = $this->survey('single_question');

        StudioBlockResponse::factory()->create([
            'studio_content_id' => $answered->id,
            'block_id' => 'x',
            'respondent_token' => 'me',
        ]);

        $this->getJson('/api/studio/content/public/catalog?type=survey&not_participated=1&respondent_token=me')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', (string) $fresh->id);
    }

    public function test_participation_aggregate_uses_a_single_query(): void
    {
        foreach (range(1, 6) as $i) {
            $poll = $this->survey('single_question', [
                'blocks' => [[
                    'id' => "b$i",
                    'type' => 'choice',
                    'config' => ['title' => 'Q', 'formOptions' => ['A', 'B']],
                ]],
            ]);
            StudioBlockResponse::factory()->create([
                'studio_content_id' => $poll->id,
                'block_id' => "b$i",
                'answer' => ['value' => 'A'],
            ]);
        }

        DB::enableQueryLog();
        $this->getJson('/api/studio/content/public/catalog?type=survey&per_page=6')->assertOk();
        $responseQueries = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'studio_block_responses'));
        DB::disableQueryLog();

        // Enrichissement participation + toggle "pas encore participé" au plus :
        // pas un agrégat par sondage.
        $this->assertLessThanOrEqual(2, $responseQueries->count());
    }
}
