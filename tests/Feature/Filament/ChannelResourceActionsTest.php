<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Channels\Pages\EditChannel;
use App\Models\Channel\Channel;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ChannelResourceActionsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): void
    {
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_suspend_action_changes_channel_status(): void
    {
        $this->actingAsAdmin();
        $channel = Channel::factory()->withProfile()->create(['status' => 'active']);

        Livewire::test(EditChannel::class, ['record' => $channel->getKey()])
            ->callAction('suspend');

        $this->assertSame('suspended', $channel->fresh()->status);
        $this->assertNotNull($channel->fresh()->suspended_until);
    }

    public function test_ban_action_changes_channel_status(): void
    {
        $this->actingAsAdmin();
        $channel = Channel::factory()->withProfile()->create(['status' => 'active']);

        Livewire::test(EditChannel::class, ['record' => $channel->getKey()])
            ->callAction('ban');

        $this->assertSame('banned', $channel->fresh()->status);
    }

    public function test_profile_is_updated_through_the_domain_action(): void
    {
        $this->actingAsAdmin();
        $channel = Channel::factory()->withProfile()->create();
        $channel->profile->update(['handle' => 'chaine_test']);

        Livewire::test(EditChannel::class, ['record' => $channel->getKey()])
            ->fillForm(['name' => 'Nouveau nom éditorial', 'handle' => 'chaine_test'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Nouveau nom éditorial', $channel->fresh()->profile->name);
    }
}
