<?php

namespace Tests\Feature\Auth;

use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegisterTest extends TestCase
{
    use RefreshDatabase;

    private array $validPayload = [
        'first_name' => 'Alice',
        'last_name'  => 'Dupont',
        'birthday'   => '1990-05-15',
        'email'      => 'alice@example.com',
        'password'   => 'password123',
    ];

    public function test_user_can_register_with_valid_data(): void
    {
        $response = $this->postJson('/api/auth/register', $this->validPayload);

        $response->assertStatus(201)
                 ->assertJsonPath('success', true)
                 ->assertJsonStructure(['data' => ['email']]);

        $this->assertDatabaseHas('users', ['email' => 'alice@example.com']);
    }

    public function test_registration_creates_user_profile(): void
    {
        $this->postJson('/api/auth/register', $this->validPayload);

        $user = User::where('email', 'alice@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->profile);
        $this->assertSame('Alice', $user->profile->first_name);
    }

    public function test_registration_fails_with_duplicate_email(): void
    {
        User::factory()->create(['email' => 'alice@example.com']);

        $response = $this->postJson('/api/auth/register', $this->validPayload);

        $response->assertStatus(422);
    }

    public function test_registration_requires_all_fields(): void
    {
        $response = $this->postJson('/api/auth/register', []);

        $response->assertStatus(422);
    }

    public function test_registration_requires_password_min_8_chars(): void
    {
        $response = $this->postJson('/api/auth/register', array_merge($this->validPayload, [
            'password' => 'short',
        ]));

        $response->assertStatus(422);
    }
}
