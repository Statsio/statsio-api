<?php

namespace Tests\Feature\Content;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentCategoryApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_exposes_the_sub_brand_of_each_category(): void
    {
        $data = collect($this->getJson('/api/content-categories')->assertOk()->json('data'));

        $this->assertSame('tvstats', $data->firstWhere('slug', 'tv')['sub_brand']);
        $this->assertSame('medistats', $data->firstWhere('slug', 'sante')['sub_brand']);
        $this->assertSame('all', $data->firstWhere('slug', 'politique')['sub_brand']);
    }

    public function test_index_filters_by_sub_brand_keeping_all_brand_categories(): void
    {
        $slugs = collect($this->getJson('/api/content-categories?sub_brand=tvstats')->assertOk()->json('data'))
            ->pluck('slug');

        $this->assertContains('tv', $slugs);
        $this->assertContains('politique', $slugs); // catégorie « toutes les marques »
        $this->assertNotContains('sante', $slugs);  // propre à Medistats
    }
}
