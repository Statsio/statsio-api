<?php

namespace Tests\Unit\Domain\Ai;

use App\Domain\Ai\BlockCatalog\StudioBlockCatalog;
use PHPUnit\Framework\TestCase;

class StudioBlockCatalogTest extends TestCase
{
    private StudioBlockCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->catalog = new StudioBlockCatalog;
    }

    public function test_covers_every_block_type_from_the_front_union(): void
    {
        // Miroir de BlockType dans statsio-front/app/types/studio.ts — à garder synchrone.
        $expected = [
            'bar', 'line', 'pie', 'table', 'kpi', 'record', 'related',
            'heading', 'paragraph', 'quote', 'callout',
            'search', 'param',
            'image', 'video', 'button', 'link-card', 'retenir', 'map', 'field-grid',
            'choice', 'checkboxes', 'dropdown', 'scale', 'rating',
            'loop', 'if',
            'sd-embed',
        ];

        sort($expected);
        $actual = $this->catalog->types();
        sort($actual);

        $this->assertSame($expected, $actual);
    }

    public function test_every_entry_has_the_required_shape(): void
    {
        foreach ($this->catalog->all() as $type => $block) {
            $this->assertArrayHasKey('category', $block, $type);
            $this->assertArrayHasKey('description', $block, $type);
            $this->assertArrayHasKey('contentTypes', $block, $type);
            $this->assertArrayHasKey('requiresDataset', $block, $type);
            $this->assertIsArray($block['fieldMapping'], $type);
            $this->assertIsArray($block['config'], $type);
        }
    }

    public function test_form_blocks_are_survey_only(): void
    {
        foreach (['choice', 'checkboxes', 'dropdown', 'scale', 'rating'] as $type) {
            $this->assertTrue($this->catalog->isAllowed($type, 'survey'), $type);
            $this->assertFalse($this->catalog->isAllowed($type, 'statsdata'), $type);
            $this->assertFalse($this->catalog->isAllowed($type, 'article'), $type);
        }
    }

    public function test_search_and_param_blocks_are_statsdata_only(): void
    {
        foreach (['search', 'param'] as $type) {
            $this->assertTrue($this->catalog->isAllowed($type, 'statsdata'), $type);
            $this->assertFalse($this->catalog->isAllowed($type, 'article'), $type);
            $this->assertFalse($this->catalog->isAllowed($type, 'survey'), $type);
        }
    }

    public function test_sd_embed_block_is_article_only(): void
    {
        $this->assertTrue($this->catalog->isAllowed('sd-embed', 'article'));
        $this->assertFalse($this->catalog->isAllowed('sd-embed', 'statsdata'));
        $this->assertFalse($this->catalog->isAllowed('sd-embed', 'survey'));
        $this->assertFalse($this->catalog->get('sd-embed')['requiresDataset']);
    }

    public function test_if_block_is_a_container_available_everywhere(): void
    {
        foreach (['statsdata', 'article', 'survey'] as $contentType) {
            $this->assertTrue($this->catalog->isAllowed('if', $contentType), $contentType);
        }
        $this->assertTrue($this->catalog->get('if')['isContainer']);
        $this->assertFalse($this->catalog->get('if')['requiresDataset']);
    }

    public function test_charts_and_text_are_available_to_all_content_types(): void
    {
        foreach (['bar', 'line', 'pie', 'table', 'kpi', 'heading', 'paragraph', 'image', 'loop'] as $type) {
            foreach (['statsdata', 'article', 'survey'] as $contentType) {
                $this->assertTrue($this->catalog->isAllowed($type, $contentType), "$type / $contentType");
            }
        }
    }

    public function test_for_content_type_filters_the_palette(): void
    {
        $article = array_keys($this->catalog->forContentType('article'));

        $this->assertContains('bar', $article);
        $this->assertNotContains('search', $article);
        $this->assertNotContains('rating', $article);
    }

    public function test_data_blocks_require_a_dataset(): void
    {
        foreach (['bar', 'line', 'pie', 'table', 'kpi', 'loop'] as $type) {
            $this->assertTrue($this->catalog->get($type)['requiresDataset'], $type);
        }

        $this->assertTrue($this->catalog->get('param')['requiresDataset']);

        foreach (['heading', 'image', 'choice', 'search', 'if'] as $type) {
            $this->assertFalse($this->catalog->get($type)['requiresDataset'], $type);
        }
    }
}
