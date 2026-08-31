<?php

namespace Tests\Unit\Domain\Ai;

use App\Domain\Ai\BlockCatalog\StudioBlockCatalog;
use App\Domain\Ai\StudioAgentPromptBuilder;
use App\Models\StudioContent;
use PHPUnit\Framework\TestCase;

class StudioAgentPromptBuilderTest extends TestCase
{
    private function builder(): StudioAgentPromptBuilder
    {
        return new StudioAgentPromptBuilder(new StudioBlockCatalog);
    }

    private function content(array $attrs): StudioContent
    {
        return (new StudioContent)->forceFill([
            'type' => 'statsdata',
            'title' => 'Prix essence',
            'pages' => [['id' => 'default', 'title' => 'Page 1']],
            'sections' => [],
            'blocks' => [],
            ...$attrs,
        ]);
    }

    public function test_flags_an_unconfigured_search_block(): void
    {
        $content = $this->content([
            'pages' => [
                ['id' => 'default', 'title' => 'Accueil'],
                ['id' => 'p2', 'title' => 'Détail par Ville', 'params' => [['name' => 'ville']]],
            ],
            'sections' => [['id' => 'sec', 'layout' => '1-col', 'pageId' => 'p2']],
            'blocks' => [[
                'id' => 'srch1', 'type' => 'search', 'zoneId' => 'sec-0',
                'fieldMapping' => [], 'config' => [],
            ]],
        ]);

        $prompt = $this->builder()->build($content);

        $this->assertStringContainsString('À CORRIGER', $prompt);
        $this->assertStringContainsString('srch1', $prompt);
    }

    public function test_no_pending_section_when_search_has_sources(): void
    {
        $content = $this->content([
            'pages' => [['id' => 'p2', 'title' => 'T', 'params' => [['name' => 'ville']]]],
            'sections' => [['id' => 'sec', 'layout' => '1-col', 'pageId' => 'p2']],
            'blocks' => [[
                'id' => 'srch1', 'type' => 'search', 'zoneId' => 'sec-0',
                'fieldMapping' => [
                    'searchSources' => [['datasetId' => '4', 'columns' => ['ville', 'cp']]],
                    'resultTitleColumn' => 'ville',
                ],
                'config' => [],
            ]],
        ]);

        $this->assertStringNotContainsString('À CORRIGER', $this->builder()->build($content));
    }

    public function test_flags_an_unconfigured_param_block(): void
    {
        $content = $this->content([
            'sections' => [['id' => 'sec', 'layout' => '1-col', 'pageId' => 'default']],
            'blocks' => [[
                'id' => 'pm1', 'type' => 'param', 'zoneId' => 'sec-0', 'datasetId' => '4',
                'fieldMapping' => [], 'config' => [],
            ]],
        ]);

        $prompt = $this->builder()->build($content);
        $this->assertStringContainsString('À CORRIGER', $prompt);
        $this->assertStringContainsString('pm1', $prompt);
        $this->assertStringContainsString('paramColumn', $prompt);
    }

    public function test_data_model_describes_the_single_page_model(): void
    {
        $prompt = $this->builder()->build($this->content([]));
        $this->assertStringContainsString('un seul type de page', $prompt);
        $this->assertStringContainsString('fanOut', $prompt);
        $this->assertStringNotContainsString('page *template*', $prompt);
    }

    public function test_palette_is_gated_by_content_type(): void
    {
        $survey = $this->content(['type' => 'survey']);
        $prompt = $this->builder()->build($survey);

        $this->assertStringContainsString('rating', $prompt);
        $this->assertStringNotContainsString('- search ', $prompt);
    }

    public function test_article_palette_exposes_the_sd_embed_block_and_reference_guidance(): void
    {
        $article = $this->content(['type' => 'article']);
        $prompt = $this->builder()->build($article);

        $this->assertStringContainsString('sd-embed', $prompt);
        $this->assertStringContainsString('Contenus référencés', $prompt);
    }

    public function test_article_prompt_carries_a_full_composition_plan(): void
    {
        $article = $this->content(['type' => 'article']);
        $prompt = $this->builder()->build($article);

        $this->assertStringContainsString('PLAN DE COMPOSITION', $prompt);
        $this->assertStringContainsString('À retenir', $prompt);
    }

    public function test_composition_plan_is_article_only(): void
    {
        foreach (['statsdata', 'survey'] as $type) {
            $this->assertStringNotContainsString(
                'PLAN DE COMPOSITION',
                $this->builder()->build($this->content(['type' => $type])),
                $type,
            );
        }
    }
}
