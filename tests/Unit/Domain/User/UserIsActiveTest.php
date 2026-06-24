<?php

namespace Tests\Unit\Domain\User;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserIsActiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_user_returns_true(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->assertTrue($user->isActive());
    }

    public function test_anonymized_user_returns_false(): void
    {
        $user = User::factory()->create(['status' => 'anonymized']);

        $this->assertFalse($user->isActive());
    }

    public function test_suspended_user_with_future_date_returns_false(): void
    {
        $user = User::factory()->create([
            'status' => 'suspended',
            'suspended_until' => now()->addDays(3),
        ]);

        $this->assertFalse($user->isActive());
    }

    public function test_suspended_user_with_past_date_returns_false(): void
    {
        $user = User::factory()->create([
            'status' => 'suspended',
            'suspended_until' => now()->subDays(1),
        ]);

        $this->assertFalse($user->isActive());
    }

    public function test_banned_user_returns_false(): void
    {
        $user = User::factory()->create(['status' => 'banned']);

        $this->assertFalse($user->isActive());
    }
}
