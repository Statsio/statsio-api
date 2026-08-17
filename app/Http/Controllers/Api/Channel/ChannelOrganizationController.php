<?php

namespace App\Http\Controllers\Api\Channel;

use App\Domain\Channel\Actions\OrganizationAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Channel\CreateOrganizationRequest;
use App\Http\Requests\Channel\LinkOrganizationRequest;
use App\Models\Channel\Channel;
use App\Models\Channel\ChannelUser;
use App\Models\Channel\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelOrganizationController extends Controller
{
    public function __construct(
        private OrganizationAction $organizationAction,
    ) {}

    /**
     * Organisations que l'utilisateur connecté peut lier à cette chaîne —
     * celles dont il possède déjà la chaîne principale.
     */
    public function joinable(Request $request, int $id): JsonResponse
    {
        $channel = Channel::findOrFail($id);
        $organizations = $this->organizationAction->joinableFor($request->user(), $channel->organization_id);

        return response()->json(['success' => true, 'data' => $organizations]);
    }

    public function store(CreateOrganizationRequest $request, int $id): JsonResponse
    {
        $channel = Channel::findOrFail($id);

        if ($channel->organization_id) {
            return response()->json(['success' => false, 'message' => 'Cette chaîne appartient déjà à une organisation.'], 422);
        }

        $organization = $this->organizationAction->create($channel, $request->validated('name'));

        return response()->json(['success' => true, 'data' => $organization->load('principalChannel.profile')], 201);
    }

    public function update(LinkOrganizationRequest $request, int $id): JsonResponse
    {
        $channel = Channel::findOrFail($id);
        $organization = Organization::findOrFail($request->validated('organization_id'));

        $this->organizationAction->link($channel, $organization);

        return response()->json(['success' => true, 'data' => $organization->load('principalChannel.profile')]);
    }

    /**
     * Quitte l'organisation — réservé au propriétaire de la chaîne (même
     * contrôle de rôle en dur que DeleteChannelRequest, pas de FormRequest
     * dédiée car il n'y a pas de corps à valider).
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (! $user || ! ChannelUser::userIsOwner($id, $user->id)) {
            return response()->json(['success' => false, 'message' => "Vous n'avez pas la permission de modifier l'organisation de cette chaîne."], 403);
        }

        $channel = Channel::findOrFail($id);
        $this->organizationAction->leave($channel);

        return response()->json(['success' => true]);
    }
}
