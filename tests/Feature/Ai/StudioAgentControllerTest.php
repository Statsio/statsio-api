<?php

namespace Tests\Feature\Ai;

use App\Models\Ai\AiConversation;
use App\Models\Ai\AiRun;
use App\Models\StudioContent;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class StudioAgentControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    private StudioContent $content;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->token = $this->user->createToken('test')->plainTextToken;
        $this->content = StudioContentFactory::new()->create(['user_id' => $this->user->id]);

        config()->set('services.ai.gemini.api_key', 'test-key');
        RateLimiter::clear('ai-agent:'.$this->user->id);
    }

    private function auth(): array
    {
        return ['Authorization' => "Bearer {$this->token}"];
    }

    private function fakeGemini(string $reply = 'Voici ce que je peux faire.'): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [['text' => $reply]]],
                    'finishReason' => 'STOP',
                ]],
                'usageMetadata' => ['promptTokenCount' => 10, 'candidatesTokenCount' => 5, 'totalTokenCount' => 15],
            ]),
        ]);
    }

    public function test_unauthenticated_is_rejected(): void
    {
        $this->postJson("/api/ai/studio/contents/{$this->content->id}/conversations")
            ->assertStatus(401);
    }

    public function test_non_editor_cannot_open_a_conversation(): void
    {
        $other = User::factory()->create();
        $token = $other->createToken('t')->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson("/api/ai/studio/contents/{$this->content->id}/conversations")
            ->assertStatus(403);
    }

    public function test_create_conversation_returns_shape(): void
    {
        $this->withHeaders($this->auth())
            ->postJson("/api/ai/studio/contents/{$this->content->id}/conversations")
            ->assertStatus(200)
            ->assertJsonPath('data.studio_content_id', $this->content->id)
            ->assertJsonStructure(['data' => ['id', 'messages', 'runs']]);

        $this->assertDatabaseHas('ai_conversations', [
            'user_id' => $this->user->id,
            'studio_content_id' => $this->content->id,
        ]);
    }

    public function test_each_create_call_makes_a_new_conversation(): void
    {
        $a = $this->withHeaders($this->auth())
            ->postJson("/api/ai/studio/contents/{$this->content->id}/conversations")->json('data.id');
        $b = $this->withHeaders($this->auth())
            ->postJson("/api/ai/studio/contents/{$this->content->id}/conversations")->json('data.id');

        $this->assertNotSame($a, $b);
        $this->assertDatabaseCount('ai_conversations', 2);
    }

    public function test_list_conversations_returns_summaries_newest_first(): void
    {
        $this->fakeGemini();

        $c1 = $this->withHeaders($this->auth())
            ->postJson("/api/ai/studio/contents/{$this->content->id}/conversations")->json('data.id');
        $c2 = $this->withHeaders($this->auth())
            ->postJson("/api/ai/studio/contents/{$this->content->id}/conversations")->json('data.id');

        // Un message dans c1 → titre + remonte en tête.
        $this->withHeaders($this->auth())
            ->postJson("/api/ai/studio/conversations/{$c1}/messages", ['text' => 'Ajoute un graphique de population']);

        $list = $this->withHeaders($this->auth())
            ->getJson("/api/ai/studio/contents/{$this->content->id}/conversations")
            ->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'title', 'message_count', 'updated_at']]])
            ->json('data');

        $this->assertSame($c1, $list[0]['id']);
        $this->assertSame('Ajoute un graphique de population', $list[0]['title']);
        $this->assertSame($c2, $list[1]['id']);
        $this->assertNull($list[1]['title']);
    }

    public function test_delete_conversation_removes_it_and_its_messages(): void
    {
        $this->fakeGemini();
        $conversation = $this->withHeaders($this->auth())
            ->postJson("/api/ai/studio/contents/{$this->content->id}/conversations")->json('data.id');
        $this->withHeaders($this->auth())
            ->postJson("/api/ai/studio/conversations/{$conversation}/messages", ['text' => 'salut']);

        $this->withHeaders($this->auth())
            ->deleteJson("/api/ai/studio/conversations/{$conversation}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('ai_conversations', ['id' => $conversation]);
        $this->assertDatabaseMissing('ai_messages', ['ai_conversation_id' => $conversation]);
        $this->assertDatabaseMissing('ai_runs', ['ai_conversation_id' => $conversation]);
    }

    public function test_cannot_delete_another_users_conversation(): void
    {
        $conversation = AiConversation::create([
            'user_id' => $this->user->id,
            'studio_content_id' => $this->content->id,
        ]);
        $other = User::factory()->create();
        $token = $other->createToken('t')->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->deleteJson("/api/ai/studio/conversations/{$conversation->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('ai_conversations', ['id' => $conversation->id]);
    }

    public function test_send_message_returns_503_when_llm_not_configured(): void
    {
        config()->set('services.ai.gemini.api_key', null);

        $conversation = $this->withHeaders($this->auth())
            ->postJson("/api/ai/studio/contents/{$this->content->id}/conversations")
            ->json('data.id');

        $this->withHeaders($this->auth())
            ->postJson("/api/ai/studio/conversations/{$conversation}/messages", ['text' => 'salut'])
            ->assertStatus(503);
    }

    public function test_send_message_runs_the_agent_and_run_becomes_done(): void
    {
        $this->fakeGemini('Je peux ajouter des blocs.');

        $conversation = $this->withHeaders($this->auth())
            ->postJson("/api/ai/studio/contents/{$this->content->id}/conversations")
            ->json('data.id');

        $send = $this->withHeaders($this->auth())
            ->postJson("/api/ai/studio/conversations/{$conversation}/messages", ['text' => 'Que peux-tu faire ?'])
            ->assertStatus(202)
            ->assertJsonStructure(['data' => ['run_id', 'conversation_id']]);

        $runId = $send->json('data.run_id');

        // QUEUE=sync : le job a déjà tourné.
        $this->withHeaders($this->auth())
            ->getJson("/api/ai/studio/runs/{$runId}")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.message', 'Je peux ajouter des blocs.')
            ->assertJsonPath('data.patch', []);

        $this->assertDatabaseHas('ai_messages', ['ai_conversation_id' => $conversation, 'role' => 'user', 'text' => 'Que peux-tu faire ?']);
        $this->assertDatabaseHas('ai_messages', ['ai_conversation_id' => $conversation, 'role' => 'model', 'text' => 'Je peux ajouter des blocs.']);
    }

    public function test_run_of_another_user_is_forbidden(): void
    {
        $conversation = AiConversation::create([
            'user_id' => $this->user->id,
            'studio_content_id' => $this->content->id,
        ]);
        $run = AiRun::create([
            'ai_conversation_id' => $conversation->id,
            'status' => AiRun::STATUS_DONE,
        ]);

        $other = User::factory()->create();
        $token = $other->createToken('t')->plainTextToken;

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson("/api/ai/studio/runs/{$run->id}")
            ->assertStatus(403);
    }

    public function test_message_is_validated(): void
    {
        $conversation = $this->withHeaders($this->auth())
            ->postJson("/api/ai/studio/contents/{$this->content->id}/conversations")
            ->json('data.id');

        $this->withHeaders($this->auth())
            ->postJson("/api/ai/studio/conversations/{$conversation}/messages", ['text' => ''])
            ->assertStatus(422);
    }
}
