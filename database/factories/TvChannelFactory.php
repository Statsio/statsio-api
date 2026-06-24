<?php

namespace Database\Factories;

use App\Models\Tv\TvChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

class TvChannelFactory extends Factory
{
    protected $model = TvChannel::class;

    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->regexify('[a-z]{4,8}-[a-z]{2,5}'),
            'number' => fake()->unique()->numberBetween(1, 999),
            'display_name' => fake()->company(),
            'epg_channel_id' => fake()->regexify('[A-Z]{2,5}[0-9]{1,3}'),
            'logo_url' => null,
            'is_active' => true,
        ];
    }
}
