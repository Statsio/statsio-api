<?php

namespace Database\Factories;

use App\Models\Studio\StudioBlockResponse;
use App\Models\StudioContent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class StudioBlockResponseFactory extends Factory
{
    protected $model = StudioBlockResponse::class;

    public function definition(): array
    {
        return [
            'studio_content_id' => StudioContent::factory(),
            'block_id' => (string) fake()->uuid(),
            'user_id' => null,
            'respondent_token' => (string) Str::uuid(),
            'answer' => ['value' => fake()->word()],
        ];
    }
}
