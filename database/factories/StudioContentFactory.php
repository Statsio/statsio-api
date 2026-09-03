<?php

namespace Database\Factories;

use App\Models\StudioContent;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
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
            'slug' => Str::slug($title).'-'.fake()->randomNumber(4),
            'pages' => null,
            'blocks' => null,
            'sections' => null,
        ];
    }

    /**
     * Publie le contenu : fige une v1 (instantané des colonnes courantes) et pointe
     * la page publique dessus — reproduit ce que fait `PublishStudioContentAction`.
     */
    public function published(): static
    {
        return $this->state(fn () => ['status' => 'published'])
            ->afterCreating(function (StudioContent $content): void {
                $version = $content->versions()->create([
                    'version' => 1,
                    'title' => $content->title,
                    'description' => $content->description,
                    'coverage' => $content->coverage,
                    'sub_brand' => $content->sub_brand?->value ?? 'statsio',
                    'categories' => $content->categories ?? [],
                    'pages' => $content->pages ?? [],
                    'sections' => $content->sections ?? [],
                    'blocks' => $content->blocks ?? [],
                    'published_as' => $content->published_as ?? 'user',
                    'channel_id' => $content->channel_id,
                    'published_by_user_id' => $content->user_id,
                    'created_at' => Carbon::now(),
                ]);

                $content->forceFill([
                    'status' => 'published',
                    'published_version_id' => $version->id,
                    'published_version' => 1,
                    'first_published_at' => Carbon::now(),
                    'last_published_at' => Carbon::now(),
                ])->save();
            });
    }
}
