<?php

namespace Tests\Unit\Domain\Content;

use App\Domain\Content\Actions\SuggestDossiersAction;
use Database\Factories\DossierFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuggestDossiersActionTest extends TestCase
{
    use RefreshDatabase;

    private SuggestDossiersAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new SuggestDossiersAction;
    }

    public function test_it_matches_a_single_token_from_the_title(): void
    {
        DossierFactory::new()->create(['name' => 'Guerre en Ukraine', 'keywords' => []]);

        $result = $this->action->execute("Le conflit en Ukraine s'enlise");

        $this->assertCount(1, $result);
        $this->assertSame('Guerre en Ukraine', $result->first()->name);
    }

    public function test_it_matches_a_multi_word_keyword_phrase(): void
    {
        DossierFactory::new()->create([
            'name' => 'Transition énergétique',
            'keywords' => ['pouvoir achat'],
        ]);

        $result = $this->action->execute('Le pouvoir achat des ménages recule');

        $this->assertCount(1, $result);
    }

    public function test_category_overlap_breaks_the_tie(): void
    {
        DossierFactory::new()->withCategories(['monde'])->create(['name' => 'Diplomatie mondiale', 'keywords' => ['sommet']]);
        DossierFactory::new()->create(['name' => 'Autre sommet', 'keywords' => ['sommet']]);

        $result = $this->action->execute('Un sommet décisif', ['monde']);

        $this->assertSame('Diplomatie mondiale', $result->first()->name);
    }

    public function test_inactive_dossiers_are_excluded(): void
    {
        DossierFactory::new()->inactive()->create(['name' => 'Ukraine', 'keywords' => []]);

        $this->assertCount(0, $this->action->execute('La situation en Ukraine'));
    }

    public function test_it_respects_the_limit(): void
    {
        foreach (range(1, 8) as $i) {
            DossierFactory::new()->create(['name' => "Climat {$i}", 'keywords' => ['climat']]);
        }

        $this->assertCount(3, $this->action->execute('Le climat change', [], 3));
    }

    public function test_a_title_without_any_match_returns_nothing(): void
    {
        DossierFactory::new()->create(['name' => 'Guerre en Ukraine', 'keywords' => ['russie']]);

        $this->assertCount(0, $this->action->execute('Recette de la tarte aux pommes'));
    }
}
