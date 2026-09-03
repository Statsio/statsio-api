<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        return [
            'user_id' => null,
            'path' => 'media/'.Str::uuid().'.png',
            'type' => 'image/png',
            'collection_name' => null,
        ];
    }
}
