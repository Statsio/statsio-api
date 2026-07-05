<?php

namespace Tests\Feature\Studio;

use App\Models\StudioContent;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudioBlockResponseControllerTest extends TestCase
{
    use RefreshDatabase;

    private function publishedContentWithChoiceBlock(): StudioContent
    {
        return StudioContentFactory::new()->published()->create([
            'blocks' => [
                [
                    'id' => 'block-1',
                    'type' => 'choice',
                    'zoneId' => 'zone-a',
                    'config' => ['title' => 'Votre couleur préférée ?', 'formOptions' => ['Rouge', 'Bleu']],
                ],
            ],
        ]);
    }

    public function test_anonymous_visitor_can_submit_a_response(): void
    {
        $content = $this->publishedContentWithChoiceBlock();
        $token = (string) Str::uuid();

        $response = $this->postJson("/api/studio/content/public/{$content->slug}/blocks/block-1/response", [
            'respondent_token' => $token,
            'value' => 'Rouge',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.answered', true);
        $response->assertJsonPath('data.my_answer', 'Rouge');

        $this->assertDatabaseHas('studio_block_responses', [
            'studio_content_id' => $content->id,
            'block_id' => 'block-1',
            'respondent_token' => $token,
            'user_id' => null,
        ]);
    }

    public function test_aggregate_reflects_option_percentages(): void
    {
        $content = $this->publishedContentWithChoiceBlock();

        $this->postJson("/api/studio/content/public/{$content->slug}/blocks/block-1/response", [
            'respondent_token' => (string) Str::uuid(),
            'value' => 'Rouge',
        ]);
        $this->postJson("/api/studio/content/public/{$content->slug}/blocks/block-1/response", [
            'respondent_token' => (string) Str::uuid(),
            'value' => 'Rouge',
        ]);
        $response = $this->postJson("/api/studio/content/public/{$content->slug}/blocks/block-1/response", [
            'respondent_token' => (string) Str::uuid(),
            'value' => 'Bleu',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('data.aggregate.total_responses', 3);

        $options = collect($response->json('data.aggregate.options'))->keyBy('value');
        $this->assertEquals(66.7, $options['Rouge']['percent']);
        $this->assertEquals(33.3, $options['Bleu']['percent']);
    }

    public function test_submitting_again_with_same_token_replaces_the_answer(): void
    {
        $content = $this->publishedContentWithChoiceBlock();
        $token = (string) Str::uuid();

        $this->postJson("/api/studio/content/public/{$content->slug}/blocks/block-1/response", [
            'respondent_token' => $token,
            'value' => 'Rouge',
        ]);
        $response = $this->postJson("/api/studio/content/public/{$content->slug}/blocks/block-1/response", [
            'respondent_token' => $token,
            'value' => 'Bleu',
        ]);

        $response->assertJsonPath('data.aggregate.total_responses', 1);
        $response->assertJsonPath('data.my_answer', 'Bleu');
        $this->assertDatabaseCount('studio_block_responses', 1);
    }

    public function test_logged_in_user_response_records_user_id(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;
        $content = $this->publishedContentWithChoiceBlock();

        $this->withToken($token)->postJson("/api/studio/content/public/{$content->slug}/blocks/block-1/response", [
            'respondent_token' => (string) Str::uuid(),
            'value' => 'Rouge',
        ])->assertStatus(200);

        $this->assertDatabaseHas('studio_block_responses', [
            'studio_content_id' => $content->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_show_returns_existing_answer_and_aggregate(): void
    {
        $content = $this->publishedContentWithChoiceBlock();
        $token = (string) Str::uuid();

        $this->postJson("/api/studio/content/public/{$content->slug}/blocks/block-1/response", [
            'respondent_token' => $token,
            'value' => 'Rouge',
        ]);

        $response = $this->getJson("/api/studio/content/public/{$content->slug}/blocks/block-1/response?respondent_token={$token}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.answered', true);
        $response->assertJsonPath('data.my_answer', 'Rouge');
    }

    public function test_submission_is_rejected_for_non_form_block(): void
    {
        $content = StudioContentFactory::new()->published()->create([
            'blocks' => [['id' => 'block-1', 'type' => 'bar', 'zoneId' => 'zone-a', 'config' => []]],
        ]);

        $response = $this->postJson("/api/studio/content/public/{$content->slug}/blocks/block-1/response", [
            'respondent_token' => (string) Str::uuid(),
            'value' => 'Rouge',
        ]);

        $response->assertStatus(422);
    }

    public function test_submissions_are_throttled(): void
    {
        $content = $this->publishedContentWithChoiceBlock();

        for ($i = 0; $i < 20; $i++) {
            $this->postJson("/api/studio/content/public/{$content->slug}/blocks/block-1/response", [
                'respondent_token' => (string) Str::uuid(),
                'value' => 'Rouge',
            ])->assertStatus(200);
        }

        $this->postJson("/api/studio/content/public/{$content->slug}/blocks/block-1/response", [
            'respondent_token' => (string) Str::uuid(),
            'value' => 'Rouge',
        ])->assertStatus(429);
    }
}
