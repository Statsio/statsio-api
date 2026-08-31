<?php

namespace Database\Factories;

use App\Domain\Identity\Enums\IdentityVerificationStatusEnum;
use App\Models\Identity\IdentityVerification;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class IdentityVerificationFactory extends Factory
{
    protected $model = IdentityVerification::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'didit_session_id' => (string) Str::uuid(),
            'didit_session_number' => fake()->numberBetween(1000, 99999),
            'status' => IdentityVerificationStatusEnum::NotStarted->value,
            'workflow_id' => (string) Str::uuid(),
            'session_url' => 'https://verify.didit.me/en/session/'.Str::random(12),
            'verified_at' => null,
            'last_event_at' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => IdentityVerificationStatusEnum::Approved->value,
            'verified_at' => now(),
            'last_event_at' => now(),
        ]);
    }
}
