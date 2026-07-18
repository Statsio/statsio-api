<?php

namespace Tests\Feature\Admin;

use App\Models\Tv\TvReviewQuestion;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReviewQuestionControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function asAdmin()
    {
        return $this->withToken($this->admin->createToken('test')->plainTextToken);
    }

    public function test_index_orders_by_sort_order(): void
    {
        // Les 7 questions par défaut sont seedées directement par la migration, triées 1..7.
        $response = $this->asAdmin()->getJson('/api/admin/tv/review-questions');

        $response->assertStatus(200);
        $sortOrders = collect($response->json())->pluck('sort_order')->all();
        $this->assertSame($sortOrders, collect($sortOrders)->sort()->values()->all());
    }

    public function test_store_creates_question(): void
    {
        $response = $this->asAdmin()->postJson('/api/admin/tv/review-questions', [
            'label' => 'Une nouvelle question ?',
        ]);

        $response->assertStatus(201)->assertJsonPath('label', 'Une nouvelle question ?');
    }

    public function test_update_changes_label_and_active_flag(): void
    {
        $question = TvReviewQuestion::first();

        $this->asAdmin()->patchJson("/api/admin/tv/review-questions/{$question->id}", [
            'label' => 'Label modifié',
            'is_active' => false,
        ])->assertStatus(200)
            ->assertJsonPath('label', 'Label modifié')
            ->assertJsonPath('is_active', false);
    }

    public function test_destroy_removes_question(): void
    {
        $question = TvReviewQuestion::first();

        $this->asAdmin()->deleteJson("/api/admin/tv/review-questions/{$question->id}")->assertStatus(204);
        $this->assertDatabaseMissing('tv_review_questions', ['id' => $question->id]);
    }
}
