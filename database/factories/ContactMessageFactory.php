<?php

namespace Database\Factories;

use App\Domain\Support\Enums\ContactReasonEnum;
use App\Models\Support\ContactMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactMessageFactory extends Factory
{
    protected $model = ContactMessage::class;

    public function definition(): array
    {
        return [
            'reason' => fake()->randomElement(ContactReasonEnum::cases())->value,
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'company' => fake()->optional()->company(),
            'message' => fake()->paragraph(),
            'status' => 'new',
        ];
    }
}
