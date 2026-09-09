<?php

namespace Tests\Feature\Studio;

use App\Models\Offer;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SurveyContentCreationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        // Offre payante autorisant la vérification d'identité — pilotée par l'admin
        // (voir Tests\Feature\Premium\SurveyIdentityVerificationTest pour le gating lui-même).
        Offer::create([
            'key' => 'premium', 'name' => 'Premium', 'price_cents' => 200, 'period' => 'mois',
            'cta_label' => 'Passer à Premium', 'position' => 1, 'allows_identity_verification' => true,
        ]);

        $this->user = User::factory()->premium()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    public function test_creating_a_survey_persists_kind_and_identity_flag(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/studio/content', [
            'title' => 'Faut-il publier les délais aux urgences ?',
            'type' => 'survey',
            'survey_kind' => 'petition',
            'requires_identity_verification' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'survey')
            ->assertJsonPath('data.survey_kind', 'petition')
            ->assertJsonPath('data.requires_identity_verification', true);

        $this->assertDatabaseHas('studio_contents', [
            'user_id' => $this->user->id,
            'type' => 'survey',
            'survey_kind' => 'petition',
            'requires_identity_verification' => true,
        ]);
    }

    public function test_survey_kind_defaults_to_single_question(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/studio/content', [
            'title' => 'Sondage express',
            'type' => 'survey',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.survey_kind', 'single_question')
            ->assertJsonPath('data.requires_identity_verification', false);
    }

    public function test_invalid_survey_kind_is_rejected(): void
    {
        $this->withToken($this->token)->postJson('/api/studio/content', [
            'title' => 'Sondage',
            'type' => 'survey',
            'survey_kind' => 'referendum',
        ])->assertStatus(422)->assertJsonValidationErrors('survey_kind');
    }

    public function test_non_survey_content_ignores_survey_fields(): void
    {
        $response = $this->withToken($this->token)->postJson('/api/studio/content', [
            'title' => 'Un article',
            'type' => 'article',
            'survey_kind' => 'long',
            'requires_identity_verification' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.survey_kind', null)
            ->assertJsonPath('data.requires_identity_verification', false);
    }

    public function test_petition_goal_and_target_can_be_set_on_update(): void
    {
        $create = $this->withToken($this->token)->postJson('/api/studio/content', [
            'title' => 'Pétition open data',
            'type' => 'survey',
            'survey_kind' => 'petition',
        ]);
        $slug = $create->json('data.slug');

        $this->withToken($this->token)->patchJson("/api/studio/content/{$slug}", [
            'petition_goal' => 50000,
            'petition_target' => 'Adressée au ministère.',
        ])->assertOk()
            ->assertJsonPath('data.petition_goal', 50000)
            ->assertJsonPath('data.petition_target', 'Adressée au ministère.');
    }
}
