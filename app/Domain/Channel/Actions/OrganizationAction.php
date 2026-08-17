<?php

namespace App\Domain\Channel\Actions;

use App\Domain\Channel\Enums\ChannelUserRoleEnum;
use App\Models\Channel\Channel;
use App\Models\Channel\Organization;
use App\Models\User\User;
use Illuminate\Database\Eloquent\Collection;

class OrganizationAction
{
    /**
     * Crée une organisation à partir de $channel, qui en devient automatiquement
     * la chaîne principale (son logo servira de badge sur les chaînes membres).
     */
    public function create(Channel $channel, string $name): Organization
    {
        $organization = Organization::create([
            'name' => $name,
            'principal_channel_id' => $channel->id,
        ]);

        $channel->update(['organization_id' => $organization->id]);

        return $organization;
    }

    /**
     * Organisations qu'un utilisateur peut lier à l'une de ses chaînes : celles
     * dont il possède déjà la chaîne principale (role owner) — pas de demande
     * d'approbation, il contrôle déjà les deux côtés de la liaison.
     */
    public function joinableFor(User $user, ?int $excludeOrganizationId = null): Collection
    {
        return Organization::query()
            ->whereHas('principalChannel.users', fn ($q) => $q
                ->where('users.id', $user->id)
                ->where('channel_users.role', ChannelUserRoleEnum::OWNER->value))
            ->when($excludeOrganizationId, fn ($q, $excluded) => $q->where('id', '!=', $excluded))
            ->with('principalChannel.profile')
            ->get();
    }

    public function link(Channel $channel, Organization $organization): Channel
    {
        $channel->update(['organization_id' => $organization->id]);

        return $channel;
    }

    /**
     * Quitte l'organisation. Si $channel en est la chaîne principale, la
     * quitter dissout l'organisation entière (les autres chaînes membres sont
     * déliées automatiquement — voir la contrainte organization_id.nullOnDelete
     * de la migration).
     */
    public function leave(Channel $channel): Channel
    {
        $organization = $channel->organization_id ? Organization::find($channel->organization_id) : null;

        if ($organization && $organization->principal_channel_id === $channel->id) {
            $organization->delete();
        } elseif ($organization) {
            $channel->update(['organization_id' => null]);
        }

        return $channel->fresh();
    }
}
