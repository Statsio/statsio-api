<?php

namespace App\Http\Controllers\Api\Channel;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Domain\Channel\Actions\ChannelAction;
use App\Domain\Channel\Actions\ChannelStatsAction;
use App\Domain\Channel\Actions\ToggleChannelFollowAction;
use App\Models\Channel\ChannelProfile;
use App\Models\Channel\ChannelCategory;
use App\Http\Requests\Channel\CreateChannelRequest;
use App\Http\Requests\Channel\UpdateChannelRequest;

class ChannelController extends Controller
{
    public function __construct(
        private ChannelAction $channelAction,
        private ChannelStatsAction $channelStatsAction,
        private ToggleChannelFollowAction $toggleChannelFollowAction
    ) {}

    /**
     * Liste toutes les catégories disponibles
     */
    public function categories()
    {
        $categories = ChannelCategory::orderBy('position')->get(['id', 'slug', 'label']);

        return response()->json(['success' => true, 'data' => $categories]);
    }

    /**
     * Crée un channel
     */
    public function create(CreateChannelRequest $request)
    {
        $data = $request->validated();
        $channel = $this->channelAction->createChannel($data);

        return response()->json([
            'success' => true,
            'message' => __('channel.created_successfully'),
            'data' => $channel
        ], 201);
    }

    /**
     * Affiche un channel spécifique
     */
    public function show(int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (!$channel) {
            return response()->json([
                'success' => false,
                'message' => __('channel.not_found')
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $channel
        ]);
    }

    /**
     * Met à jour les médias (logo/bannière) d'un channel
     */
    public function updateMedia(Request $request, int $id)
    {
        $request->validate([
            'logo'   => 'sometimes|file|image:allow_svg|max:5120',
            'banner' => 'sometimes|file|image:allow_svg|max:10240',
        ]);

        $channel = $this->channelAction->getChannelById($id);

        if (!$channel || !$channel->profile) {
            return response()->json(['success' => false, 'message' => __('channel.not_found')], 404);
        }

        $data = $request->only(['logo', 'banner']);
        $updated = $this->channelAction->updateChannelProfile($channel->profile, $data);

        return response()->json([
            'success' => true,
            'message' => 'Médias mis à jour.',
            'data'    => $channel->fresh(['profile']),
        ]);
    }

    /**
     * Met à jour un channel
     */
    public function update(UpdateChannelRequest $request, int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (!$channel || !$channel->profile) {
            return response()->json([
                'success' => false,
                'message' => __('channel.not_found')
            ], 404);
        }

        $data = $request->validated();
        $updated = $this->channelAction->updateChannelProfile($channel->profile, $data);

        return response()->json([
            'success' => true,
            'message' => __('channel.updated_successfully'),
            'data' => $updated
        ]);
    }

    /**
     * Supprime un channel
     */
    public function destroy(int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (!$channel) {
            return response()->json([
                'success' => false,
                'message' => __('channel.not_found')
            ], 404);
        }

        $this->channelAction->deleteChannel($channel);

        return response()->json([
            'success' => true,
            'message' => __('channel.deleted_successfully')
        ]);
    }

    /**
     * Liste publique des channels actifs (annuaire /chaines).
     * Filtres: search, category (slug), sort (popular|views|name|recent).
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 15);
        $filters = $request->only(['search', 'category', 'sort']);
        $channels = $this->channelAction->getAllChannels($perPage, $filters, $request->user('api')?->id);

        return response()->json([
            'success' => true,
            'data' => $channels
        ]);
    }

    /**
     * Liste les channels de l'utilisateur connecté
     */
    public function myChannels(Request $request)
    {
        $perPage = $request->get('per_page', 50);
        $user = Auth::user();

        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $channels = $this->channelAction->getChannelsForUser($user->id, $perPage);

        return response()->json([
            'success' => true,
            'data' => $channels
        ]);
    }

    /**
     * Liste les membres de l'équipe (owner, admin, moderator)
     */
    public function members(int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (!$channel) {
            return response()->json(['success' => false, 'message' => __('channel.not_found')], 404);
        }

        $members = $channel->managementTeam()
            ->with('profile')
            ->get()
            ->map(fn ($user) => [
                'id'       => $user->id,
                'email'    => $user->email,
                'name'     => trim(($user->profile?->first_name ?? '') . ' ' . ($user->profile?->last_name ?? '')) ?: $user->email,
                'avatar'   => $user->profile?->avatar ?? null,
                'role'     => $user->pivot->role,
                'joined_at'=> $user->pivot->created_at,
            ]);

        return response()->json(['success' => true, 'data' => $members]);
    }

    /**
     * Statistiques du dashboard (vues 30j, croissance abonnés, taille de l'équipe)
     */
    public function stats(int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (!$channel) {
            return response()->json(['success' => false, 'message' => __('channel.not_found')], 404);
        }

        return response()->json(['success' => true, 'data' => $this->channelStatsAction->getStats($channel)]);
    }

    /**
     * Enregistre une vue publique de la chaîne (appelé depuis la page publique)
     */
    public function recordView(int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (!$channel) {
            return response()->json(['success' => false, 'message' => __('channel.not_found')], 404);
        }

        $this->channelStatsAction->recordView($channel);

        return response()->json(['success' => true]);
    }

    /**
     * Liste les abonnés
     */
    public function subscribers(Request $request, int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (!$channel) {
            return response()->json(['success' => false, 'message' => __('channel.not_found')], 404);
        }

        $perPage = $request->get('per_page', 20);

        $subscribers = $channel->subscribers()
            ->with('profile')
            ->paginate($perPage)
            ->through(fn ($user) => [
                'id'            => $user->id,
                'email'         => $user->email,
                'name'          => trim(($user->profile?->first_name ?? '') . ' ' . ($user->profile?->last_name ?? '')) ?: $user->email,
                'avatar'        => $user->profile?->avatar ?? null,
                'subscribed_at' => $user->pivot->subscribed_at,
            ]);

        return response()->json(['success' => true, 'data' => $subscribers]);
    }

    /**
     * Bascule l'abonnement (follow/unfollow) de l'utilisateur connecté à la chaîne
     */
    public function toggleFollow(Request $request, int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (!$channel) {
            return response()->json(['success' => false, 'message' => __('channel.not_found')], 404);
        }

        $result = $this->toggleChannelFollowAction->execute($channel, $request->user()->id);

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * Suspend un channel
     */
    public function suspend(Request $request, int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (!$channel) {
            return response()->json([
                'success' => false,
                'message' => __('channel.not_found')
            ], 404);
        }

        $updated = $this->channelAction->suspendChannel($channel);

        return response()->json([
            'success' => true,
            'message' => __('channel.suspended_successfully'),
            'data' => $updated
        ]);
    }

    /**
     * Bannit un channel
     */
    public function ban(int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (!$channel) {
            return response()->json([
                'success' => false,
                'message' => __('channel.not_found')
            ], 404);
        }

        $updated = $this->channelAction->banChannel($channel);

        return response()->json([
            'success' => true,
            'message' => __('channel.banned_successfully'),
            'data' => $updated
        ]);
    }

    /**
     * Active un channel
     */
    public function activate(int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (!$channel) {
            return response()->json([
                'success' => false,
                'message' => __('channel.not_found')
            ], 404);
        }

        $updated = $this->channelAction->activateChannel($channel);

        return response()->json([
            'success' => true,
            'message' => __('channel.activated_successfully'),
            'data' => $updated
        ]);
    }

    /**
     * Anonymise un channel
     */
    public function anonymize(int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (!$channel) {
            return response()->json([
                'success' => false,
                'message' => __('channel.not_found')
            ], 404);
        }

        $updated = $this->channelAction->anonymizeChannel($channel);

        return response()->json([
            'success' => true,
            'message' => __('channel.anonymized_successfully'),
            'data' => $updated
        ]);
    }
}
