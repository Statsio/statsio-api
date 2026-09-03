<?php

namespace Database\Factories;

use App\Models\Content\ContentCategory;
use App\Models\Content\Dossier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Dossier>
 */
class DossierFactory extends Factory
{
    protected $model = Dossier::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'name' => Str::ucfirst($name),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'description' => fake()->sentence(),
            'cover_path' => null,
            'keywords' => fake()->words(3),
            'position' => 0,
            'is_active' => true,
            'is_pinned' => false,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function pinned(): static
    {
        return $this->state(fn () => ['is_pinned' => true]);
    }

    /**
     * @param  list<string>  $slugs
     */
    public function withCategories(array $slugs): static
    {
        return $this->afterCreating(function (Dossier $dossier) use ($slugs): void {
            $ids = ContentCategory::whereIn('slug', $slugs)->pluck('id');
            $dossier->contentCategories()->sync($ids);
        });
    }
}
