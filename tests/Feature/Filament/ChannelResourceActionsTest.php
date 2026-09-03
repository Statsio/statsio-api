<?php

namespace Tests\Feature\Filament;

use App\Domain\Content\Enums\SubBrandEnum;
use App\Filament\Resources\Channels\Pages\EditChannel;
use App\Models\Channel\Channel;
use App\Models\Channel\ChannelCategory;
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

    public function test_admin_can_set_the_channel_domain(): void
    {
        $this->actingAsAdmin();
        $channel = Channel::factory()->withProfile()->create();
        $channel->profile->update(['handle' => 'chaine_domaine']);

        Livewire::test(EditChannel::class, ['record' => $channel->getKey()])
            ->assertFormSet(['sub_brand' => SubBrandEnum::All->value])
            ->fillForm(['sub_brand' => SubBrandEnum::Tvstats->value])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(SubBrandEnum::Tvstats, $channel->fresh()->profile->sub_brand);
    }

    public function test_changing_the_domain_prunes_categories_outside_it(): void
    {
        $this->actingAsAdmin();

        ChannelCategory::where('slug', 'sport')->update(['sub_brand' => SubBrandEnum::Tvstats->value]);
        // 'politique' reste 'all' (défaut).

        $channel = Channel::factory()->withProfile()->create();
        $channel->profile->update(['handle' => 'chaine_prune']);
        $channel->profile->channelCategories()->sync(
            ChannelCategory::whereIn('slug', ['sport', 'politique'])->pluck('id'),
        );

        Livewire::test(EditChannel::class, ['record' => $channel->getKey()])
            ->assertFormSet(fn (array $state): bool => in_array('sport', $state['categories'], true))
            ->fillForm(['sub_brand' => SubBrandEnum::Medistats->value])
            ->assertFormSet(fn (array $state): bool => ! in_array('sport', $state['categories'], true)
                && in_array('politique', $state['categories'], true))
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(
            ['politique'],
            $channel->fresh()->profile->channelCategories->pluck('slug')->all(),
        );
    }
}
