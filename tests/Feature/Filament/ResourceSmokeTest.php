<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Channels\ChannelResource;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use App\Filament\Resources\SourceProvenances\SourceProvenanceResource;
use App\Filament\Resources\TvBroadcasts\TvBroadcastResource;
use App\Filament\Resources\TvCategories\TvCategoryResource;
use App\Filament\Resources\TvChannels\TvChannelResource;
use App\Filament\Resources\TvPrograms\TvProgramResource;
use App\Filament\Resources\TvReviewQuestions\TvReviewQuestionResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Channel\Channel;
use App\Models\Support\ContactMessage;
use App\Models\User\User;
use Database\Factories\TvBroadcastFactory;
use Database\Factories\TvChannelFactory;
use Database\Factories\TvProgramFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResourceSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function resourceProvider(): array
    {
        return [
            'users' => [UserResource::class],
            'channels' => [ChannelResource::class],
            'tv channels' => [TvChannelResource::class],
            'tv programs' => [TvProgramResource::class],
            'tv broadcasts' => [TvBroadcastResource::class],
            'tv categories' => [TvCategoryResource::class],
            'tv review questions' => [TvReviewQuestionResource::class],
            'source provenances' => [SourceProvenanceResource::class],
            'contact messages' => [ContactMessageResource::class],
        ];
    }

    /**
     * @dataProvider resourceProvider
     */
    public function test_resource_index_page_loads(string $resource): void
    {
        $this->actingAs($this->admin())
            ->get($resource::getUrl('index'))
            ->assertOk();
    }

    public function test_user_edit_page_loads(): void
    {
        $target = User::factory()->create();

        $this->actingAs($this->admin())
            ->get(UserResource::getUrl('edit', ['record' => $target]))
            ->assertOk();
    }

    public function test_channel_edit_page_loads(): void
    {
        $channel = Channel::factory()->withProfile()->create();

        $this->actingAs($this->admin())
            ->get(ChannelResource::getUrl('edit', ['record' => $channel]))
            ->assertOk();
    }

    public function test_tv_channel_edit_page_loads(): void
    {
        $channel = TvChannelFactory::new()->create();

        $this->actingAs($this->admin())
            ->get(TvChannelResource::getUrl('edit', ['record' => $channel]))
            ->assertOk();
    }

    public function test_tv_broadcast_edit_page_loads(): void
    {
        $broadcast = TvBroadcastFactory::new()->create();

        $this->actingAs($this->admin())
            ->get(TvBroadcastResource::getUrl('edit', ['record' => $broadcast]))
            ->assertOk();
    }

    public function test_tv_program_edit_page_loads(): void
    {
        $program = TvProgramFactory::new()->create();

        $this->actingAs($this->admin())
            ->get(TvProgramResource::getUrl('edit', ['record' => $program]))
            ->assertOk();
    }

    public function test_contact_message_edit_page_loads(): void
    {
        $message = ContactMessage::factory()->create();

        $this->actingAs($this->admin())
            ->get(ContactMessageResource::getUrl('edit', ['record' => $message]))
            ->assertOk();
    }
}
