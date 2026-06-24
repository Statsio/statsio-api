<?php

namespace Database\Factories;

use App\Models\StudioContent;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StudioContentFactory extends Factory
{
    protected $model = StudioContent::class;

    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'user_id' => User::factory(),
            'title' => $title,
            'description' => fake()->sentence(),
            'status' => 'draft',
            'slug' => Str::slug($title) . '-' . fake()->randomNumber(4),
            'pages' => null,
            'blocks' => null,
            'sections' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => ['status' => 'published']);
    }
}
