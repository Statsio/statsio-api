<?php

namespace Tests\Feature\Studio;

use App\Models\StudioContent;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Réglages de diffusion d'un sondage (onglet « Réglages » du Studio) :
 * ouverture, une réponse par compte, visibilité des résultats.
 */
class SurveySettingsTest extends TestCase
{
    use RefreshDatabase;

    private function survey(array $overrides = []): StudioContent
    {
        return StudioContentFactory::new()->published()->create(array_merge([
            'type' => 'survey',
            'blocks' => [[
                'id' => 'block-1',
                'type' => 'choice',
                'zoneId' => 'zone-a',
                'config' => ['title' => 'Pour ou contre ?', 'formOptions' => ['Pour', 'Contre']],
            ]],
        ], $overrides));
    }

    private function vote(StudioContent $content, string $authToken, string $value = 'Pour', ?string $respondent = null)
    {
        return $this->withToken($authToken)->postJson(
            "/api/studio/content/public/{$content->slug}/blocks/block-1/response",
            ['respondent_token' => $respondent ?? (string) Str::uuid(), 'value' => $value],
        );
    }

    private function userToken(): string
    {
        return User::factory()->create()->createToken('test')->plainTextToken;
    }

    public function test_survey_not_yet_open_rejects_responses(): void
    {
        $content = $this->survey(['opens_at' => now()->addDay()]);

        $this->vote($content, $this->userToken())
            ->assertStatus(422)
            ->assertJsonPath('errors.block_id.0', "Ce sondage n'est pas encore ouvert.");
    }

    public function test_one_response_per_account_rejects_a_second_answer(): void
    {
        $content = $this->survey(['one_response_per_account' => true]);
        $token = $this->userToken();
        $respondent = (string) Str::uuid();

        $this->vote($content, $token, 'Pour', $respondent)->assertOk()->assertJsonPath('data.can_change', false);
        // Même compte, autre appareil (autre jeton de répondant) : refusé aussi.
        $this->vote($content, $token, 'Contre')->assertStatus(422);
        $this->vote($content, $token, 'Contre', $respondent)->assertStatus(422);

        $this->assertDatabaseCount('studio_block_responses', 1);
    }

    public function test_answers_stay_editable_when_multiple_answers_allowed(): void
    {
        $content = $this->survey(['one_response_per_account' => false]);
        $token = $this->userToken();
        $respondent = (string) Str::uuid();

        $this->vote($content, $token, 'Pour', $respondent)->assertOk()->assertJsonPath('data.can_change', true);
        $this->vote($content, $token, 'Contre', $respondent)->assertOk()->assertJsonPath('data.my_answer', 'Contre');
    }

    public function test_results_hidden_until_close_when_visibility_is_at_close(): void
    {
        $content = $this->survey([
            'results_visibility' => 'at_close',
            'response_deadline' => now()->addWeek(),
        ]);

        $this->vote($content, $this->userToken())
            ->assertOk()
            ->assertJsonPath('data.results_hidden', true)
            ->assertJsonPath('data.results_hidden_reason', 'at_close')
            ->assertJsonPath('data.aggregate', null);
    }

    public function test_results_never_public_but_visible_to_owner(): void
    {
        $owner = User::factory()->create();
        $content = $this->survey(['results_visibility' => 'never', 'user_id' => $owner->id]);

        $this->vote($content, $this->userToken())
            ->assertOk()
            ->assertJsonPath('data.results_hidden_reason', 'never');

        // Le guard sanctum garde l'utilisateur résolu entre deux requêtes d'un même test.
        $this->app['auth']->forgetGuards();
        $this->withToken($owner->createToken('test')->plainTextToken)
            ->getJson("/api/studio/content/public/{$content->slug}/blocks/block-1/response?respondent_token=x")
            ->assertOk()
            ->assertJsonPath('data.results_hidden', false)
            ->assertJsonPath('data.aggregate.total_responses', 1);
    }

    public function test_results_after_submit_hidden_before_answering(): void
    {
        $content = $this->survey(['results_visibility' => 'after_submit']);

        $this->getJson("/api/studio/content/public/{$content->slug}/blocks/block-1/response?respondent_token=nobody")
            ->assertOk()
            ->assertJsonPath('data.results_hidden_reason', 'after_submit');

        $this->vote($content, $this->userToken())
            ->assertOk()
            ->assertJsonPath('data.results_hidden', false)
            ->assertJsonPath('data.aggregate.total_responses', 1);
    }

    public function test_owner_can_update_survey_settings_and_dates_must_be_ordered(): void
    {
        $owner = User::factory()->create();
        $content = StudioContentFactory::new()->create(['type' => 'survey', 'user_id' => $owner->id]);
        $auth = $owner->createToken('test')->plainTextToken;

        $this->withToken($auth)->patchJson("/api/studio/content/{$content->slug}", [
            'one_response_per_account' => false,
            'results_visibility' => 'at_close',
            'opens_at' => '2026-12-01T00:00:00Z',
            'response_deadline' => '2026-12-31T23:59:59Z',
        ])->assertOk()
            ->assertJsonPath('data.one_response_per_account', false)
            ->assertJsonPath('data.results_visibility', 'at_close');

        $this->withToken($auth)->patchJson("/api/studio/content/{$content->slug}", [
            'response_deadline' => '2026-11-15T00:00:00Z',
        ])->assertStatus(422)->assertJsonValidationErrors('response_deadline');
    }

    public function test_number_question_aggregates_average_and_median(): void
    {
        $content = $this->survey([
            'one_response_per_account' => false,
            'blocks' => [['id' => 'block-1', 'type' => 'number', 'zoneId' => 'zone-a', 'config' => ['title' => 'Combien de jours ?']]],
        ]);
        $token = $this->userToken();
        foreach ([10, 20, 60] as $v) {
            $this->vote($content, $token, (string) $v)->assertOk();
        }

        $this->vote($content, $token, '30')
            ->assertOk()
            ->assertJsonPath('data.aggregate.total_responses', 4)
            ->assertJsonPath('data.aggregate.median', 25)
            ->assertJsonPath('data.aggregate.average', 30);

        $this->vote($content, $token, 'beaucoup')->assertStatus(422);
    }

    public function test_text_answer_only_publishes_the_count(): void
    {
        $content = $this->survey([
            'blocks' => [['id' => 'block-1', 'type' => 'text-answer', 'zoneId' => 'zone-a', 'config' => ['title' => 'Un mot ?']]],
        ]);

        $response = $this->vote($content, $this->userToken(), 'Trop long à obtenir')->assertOk();
        $this->assertSame(['total_responses' => 1], $response->json('data.aggregate'));
    }
}
