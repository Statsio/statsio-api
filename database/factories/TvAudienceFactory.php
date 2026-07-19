<?php

namespace Database\Factories;

use App\Models\Tv\TvAudience;
use Illuminate\Database\Eloquent\Factories\Factory;

class TvAudienceFactory extends Factory
{
    protected $model = TvAudience::class;

    public function definition(): array
    {
        return [
            'broadcast_id' => TvBroadcastFactory::new(),
            'viewers' => fake()->numberBetween(100, 10000),
            'pda' => fake()->randomFloat(2, 0, 30),
            'rank' => fake()->numberBetween(1, 20),
        ];
    }
}
