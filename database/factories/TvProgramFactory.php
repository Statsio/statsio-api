<?php

namespace Database\Factories;

use App\Models\Tv\TvProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

class TvProgramFactory extends Factory
{
    protected $model = TvProgram::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'tv_channel_id' => TvChannelFactory::new()->create()->slug,
            'type' => fake()->randomElement(['series', 'movie', 'news', 'sport', 'documentary']),
            'description' => fake()->paragraph(),
            'is_tvstats_pick' => false,
        ];
    }
}
