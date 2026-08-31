<?php

namespace App\Http\Controllers\Api\Admin;

use App\Domain\Channel\Actions\ChannelAction;
use App\Domain\Channel\Enums\ChannelBadgeEnum;
use App\Domain\Channel\Enums\ChannelCategoryEnum;
use App\Http\Controllers\Controller;
use App\Models\Channel\Channel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Gestion admin des chaînes éditoriales (Channel/ChannelProfile) — distinct de
 * AdminChannelController, qui gère les chaînes TV (TvChannel).
 */
class AdminEditorialChannelController extends Controller
{
    public function __construct(
        private ChannelAction $channelAction,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Channel::query()
            ->with(['profile', 'owners.profile', 'channelBadges', 'organization.principalChannel.profile'])
            ->withCount('subscribers');

        if ($search = $request->input('search')) {
            $like = '%'.mb_strtolower($search).'%';
            $query->whereHas('profile', function ($q) use ($like) {
                $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(handle) LIKE ?', [$like]);
            });
        }

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $channels = $query->latest()->paginate($request->input('per_page', 25));

        return response()->json($channels);
    }

    public function show(int $id): JsonResponse
    {
        $channel = $this->detail($id);
        if (! $channel) {
            return response()->json(['message' => __('channel.not_found')], 404);
        }

        return response()->json(['data' => $channel]);
    }

    /**
     * Charge une chaîne avec les relations attendues par la vue détail/formulaire
     * admin (profil + catégories + équipe complète) — utilisé par show/update et
     * par chaque action de modération pour que leur réponse ait toujours la même
     * forme, quelle que soit l'action qui l'a déclenchée.
     */
    private function detail(int $id): ?Channel
    {
        return Channel::with(['profile.channelCategories', 'managementTeam.profile', 'channelBadges', 'organization.principalChannel.profile'])
            ->withCount('subscribers')
            ->find($id);
    }

    /**
     * Modifie le profil d'une chaîne en tant qu'admin — bypass volontaire des
     * permissions membres (channel.edit_*) puisque la route est déjà réservée
     * aux admins de la plateforme par le middleware `admin`.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $channel = Channel::with('profile')->findOrFail($id);

        if (! $channel->profile) {
            return response()->json(['message' => "Cette chaîne n'a pas de profil."], 404);
        }

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'handle' => [
                'sometimes', 'string', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/',
                Rule::unique('channel_profiles', 'handle')->ignore($channel->profile->id),
            ],
            'description' => 'sometimes|nullable|string|max:1000',
            'categories' => 'sometimes|array',
            'categories.*' => ['string', Rule::in(ChannelCategoryEnum::values())],
            'custom_color_primary' => 'sometimes|nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'custom_color_secondary' => 'sometimes|nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'country' => 'sometimes|nullable|string|max:2',
        ]);

        $this->channelAction->updateChannelProfile($channel->profile, $data);

        return response()->json(['data' => $this->detail($id)]);
    }

    public function suspend(int $id): JsonResponse
    {
        $channel = $this->channelAction->getChannelById($id);
        if (! $channel) {
            return response()->json(['message' => __('channel.not_found')], 404);
        }

        $this->channelAction->suspendChannel($channel);

        return response()->json(['data' => $this->detail($id)]);
    }

    public function ban(int $id): JsonResponse
    {
        $channel = $this->channelAction->getChannelById($id);
        if (! $channel) {
            return response()->json(['message' => __('channel.not_found')], 404);
        }

        $this->channelAction->banChannel($channel);

        return response()->json(['data' => $this->detail($id)]);
    }

    public function activate(int $id): JsonResponse
    {
        $channel = $this->channelAction->getChannelById($id);
        if (! $channel) {
            return response()->json(['message' => __('channel.not_found')], 404);
        }

        $this->channelAction->activateChannel($channel);

        return response()->json(['data' => $this->detail($id)]);
    }

    public function anonymize(int $id): JsonResponse
    {
        $channel = $this->channelAction->getChannelById($id);
        if (! $channel) {
            return response()->json(['message' => __('channel.not_found')], 404);
        }

        $this->channelAction->anonymizeChannel($channel);

        return response()->json(['data' => $this->detail($id)]);
    }

    public function syncBadges(Request $request, int $id): JsonResponse
    {
        $channel = Channel::findOrFail($id);

        $data = $request->validate([
            'badges' => 'sometimes|array',
            'badges.*' => ['string', Rule::in(ChannelBadgeEnum::values())],
        ]);

        $this->channelAction->syncBadges($channel, $data['badges'] ?? []);

        return response()->json(['data' => $this->detail($id)]);
    }

    public function destroy(int $id): JsonResponse
    {
        $channel = $this->channelAction->getChannelById($id);
        if (! $channel) {
            return response()->json(['message' => __('channel.not_found')], 404);
        }

        $this->channelAction->deleteChannel($channel);

        return response()->json(['message' => 'Chaîne supprimée.']);
    }
}
