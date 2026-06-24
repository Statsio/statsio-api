<?php

namespace Database\Factories;

use App\Models\Channel\Channel;
use App\Models\Channel\ChannelProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChannelProfileFactory extends Factory
{
    protected $model = ChannelProfile::class;

    public function definition(): array
    {
        return [
            'channel_id' => Channel::factory(),
            'name' => fake()->company(),
            'handle' => fake()->unique()->regexify('[a-z]{3,10}-[a-z]{3,6}'),
            'description' => fake()->sentence(),
        ];
    }
}
