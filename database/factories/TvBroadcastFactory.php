<?php

namespace Database\Factories;

use App\Models\Tv\TvBroadcast;
use Illuminate\Database\Eloquent\Factories\Factory;

class TvBroadcastFactory extends Factory
{
    protected $model = TvBroadcast::class;

    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-1 week', 'now');
        $end = (clone $start)->modify('+1 hour 30 minutes');

        return [
            'program_id' => TvProgramFactory::new(),
            'tv_channel_id' => TvChannelFactory::new()->create()->slug,
            'start_at' => $start,
            'end_at' => $end,
        ];
    }
}
