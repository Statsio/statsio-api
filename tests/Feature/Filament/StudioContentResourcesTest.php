<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Statsdatas\Pages\EditStatsdata;
use App\Filament\Resources\Statsdatas\StatsdataResource;
use App\Filament\Resources\Surveys\Pages\CreateSurvey;
use App\Filament\Resources\Surveys\Pages\ListSurveys;
use App\Filament\Resources\Surveys\SurveyResource;
use App\Models\StudioContent;
use App\Models\User\User;
use Database\Factories\StudioContentFactory;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudioContentResourcesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_each_content_resource_index_lists_only_its_type(): void
    {
        $article = StudioContentFactory::new()->create(['type' => 'article']);
        $statsdata = StudioContentFactory::new()->create(['type' => 'statsdata']);
        $survey = StudioContentFactory::new()->create(['type' => 'survey']);

        $this->actingAs($this->admin());

        Livewire::test(ListArticles::class)
            ->assertCanSeeTableRecords([$article])
            ->assertCanNotSeeTableRecords([$statsdata, $survey]);

        Livewire::test(ListSurveys::class)
            ->assertCanSeeTableRecords([$survey])
            ->assertCanNotSeeTableRecords([$article, $statsdata]);
    }

    public function test_admin_can_edit_a_statsdata_content(): void
    {
        $content = StudioContentFactory::new()->create(['type' => 'statsdata', 'title' => 'Ancien titre']);

        $this->actingAs($this->admin())
            ->get(StatsdataResource::getUrl('edit', ['record' => $content]))
            ->assertOk();

        Livewire::test(EditStatsdata::class, ['record' => $content->getKey()])
            ->fillForm(['title' => 'Nouveau titre', 'status' => 'published'])
            ->call('save')
            ->assertHasNoFormErrors();

        $content->refresh();
        $this->assertSame('Nouveau titre', $content->title);
        $this->assertSame('published', $content->status);
    }

    public function test_admin_can_create_an_article_with_generated_slug_and_type(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);

        Livewire::test(CreateArticle::class)
            ->fillForm([
                'title' => 'Un article de test',
                'status' => 'draft',
                'visibility' => 'private',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $content = StudioContent::where('title', 'Un article de test')->firstOrFail();
        $this->assertSame('article', $content->type);
        $this->assertSame($admin->id, $content->user_id);
        $this->assertSame('un-article-de-test', $content->slug);
    }

    public function test_admin_can_create_a_petition_survey(): void
    {
        $this->actingAs($this->admin());

        Livewire::test(CreateSurvey::class)
            ->fillForm([
                'title' => 'Pétition test',
                'status' => 'draft',
                'visibility' => 'public',
                'survey_kind' => 'petition',
                'petition_goal' => 1000,
                'petition_target' => 'Le ministère',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $content = StudioContent::where('title', 'Pétition test')->firstOrFail();
        $this->assertSame('survey', $content->type);
        $this->assertSame('petition', $content->survey_kind);
        $this->assertSame(1000, $content->petition_goal);
    }

    public function test_content_resources_are_delete_capable(): void
    {
        $content = StudioContentFactory::new()->create(['type' => 'article']);

        $this->actingAs($this->admin());

        Livewire::test(EditArticle::class, ['record' => $content->getKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($content);
    }

    public function test_article_resource_url_helpers_work(): void
    {
        $this->assertStringContainsString('/admin/articles', ArticleResource::getUrl('index'));
        $this->assertStringContainsString('/admin/surveys', SurveyResource::getUrl('index'));
    }
}
