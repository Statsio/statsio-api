<?php

namespace Database\Factories;

use App\Models\Channel\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChannelFactory extends Factory
{
    protected $model = Channel::class;

    public function definition(): array
    {
        return [
            'status' => 'active',
        ];
    }

    public function withProfile(): static
    {
        return $this->has(ChannelProfileFactory::new(), 'profile');
    }
}
