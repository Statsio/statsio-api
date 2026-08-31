<?php

namespace Database\Factories;

use App\Domain\Channel\Enums\ChannelKindEnum;
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
            'kind' => fake()->randomElement(ChannelKindEnum::values()),
            'description' => fake()->sentence(),
        ];
    }
}
