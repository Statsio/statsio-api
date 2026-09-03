<?php

namespace App\Http\Controllers\Api\Channel;

use App\Domain\Channel\Actions\ChannelAction;
use App\Domain\Channel\Actions\ChannelCatalogAction;
use App\Domain\Channel\Actions\ChannelDataSourcesAction;
use App\Domain\Channel\Actions\ChannelInvitationAction;
use App\Domain\Channel\Actions\ChannelStatsAction;
use App\Domain\Channel\Actions\ToggleChannelFollowAction;
use App\Domain\Channel\Enums\ChannelPermissionEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Channel\CreateChannelRequest;
use App\Http\Requests\Channel\DeleteChannelRequest;
use App\Http\Requests\Channel\InviteChannelMembersRequest;
use App\Http\Requests\Channel\UpdateChannelRequest;
use App\Models\Channel\ChannelCategory;
use App\Models\Channel\ChannelInvitation;
use App\Models\Channel\ChannelUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ChannelController extends Controller
{
    public function __construct(
        private ChannelAction $channelAction,
        private ChannelStatsAction $channelStatsAction,
        private ChannelDataSourcesAction $channelDataSourcesAction,
        private ToggleChannelFollowAction $toggleChannelFollowAction,
        private ChannelCatalogAction $channelCatalogAction,
        private ChannelInvitationAction $channelInvitationAction
    ) {}

    /**
     * Catalogue des permissions assignables, groupé par catégorie (public).
     */
    public function permissionsCatalog()
    {
        return response()->json(['success' => true, 'data' => ChannelPermissionEnum::grouped()]);
    }

    /**
     * Liste toutes les catégories disponibles
     */
    public function categories(Request $request)
    {
        $categories = ChannelCategory::query()
            ->forSubBrand($request->query('sub_brand'))
            ->orderBy('position')
            ->get(['id', 'slug', 'label', 'sub_brand']);

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
            'data' => $channel,
        ], 201);
    }

    /**
     * Affiche un channel spécifique. Si la chaîne est privée (voir
     * ChannelProfile::is_private), seuls ses membres (équipe ou abonnés déjà
     * existants) peuvent la consulter — les autres visiteurs reçoivent un 404,
     * comme si la chaîne n'existait pas.
     */
    public function show(Request $request, int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (! $channel) {
            return response()->json([
                'success' => false,
                'message' => __('channel.not_found'),
            ], 404);
        }

        if ($channel->profile?->is_private) {
            $user = $request->user('api');
            $isMember = $user && $channel->users()->where('users.id', $user->id)->exists();

            if (! $isMember) {
                return response()->json([
                    'success' => false,
                    'message' => __('channel.not_found'),
                ], 404);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $channel,
        ]);
    }

    /**
     * Met à jour les médias (logo/bannière) d'un channel
     */
    public function updateMedia(Request $request, int $id)
    {
        $user = $request->user();
        $canEdit = $user && (
            ChannelUser::userIsOwner($id, $user->id)
            || ChannelUser::userHasPermission($id, $user->id, 'channel.edit_profile')
            || ChannelUser::userHasPermission($id, $user->id, 'channel.edit_appearance')
        );

        if (! $canEdit) {
            return response()->json(['success' => false, 'message' => "Vous n'avez pas la permission de modifier cette chaîne."], 403);
        }

        $request->validate([
            'logo' => 'sometimes|file|image:allow_svg|max:5120',
            'banner' => 'sometimes|file|image:allow_svg|max:10240',
            'logo_media_id' => 'sometimes|integer|exists:media,id',
            'banner_media_id' => 'sometimes|integer|exists:media,id',
        ]);

        $channel = $this->channelAction->getChannelById($id);

        if (! $channel || ! $channel->profile) {
            return response()->json(['success' => false, 'message' => __('channel.not_found')], 404);
        }

        $data = $request->only(['logo', 'banner', 'logo_media_id', 'banner_media_id']);
        $data['media_owner_id'] = $user->id;
        $updated = $this->channelAction->updateChannelProfile($channel->profile, $data);

        return response()->json([
            'success' => true,
            'message' => 'Médias mis à jour.',
            'data' => $channel->fresh(['profile']),
        ]);
    }

    /**
     * Met à jour un channel
     */
    public function update(UpdateChannelRequest $request, int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (! $channel || ! $channel->profile) {
            return response()->json([
                'success' => false,
                'message' => __('channel.not_found'),
            ], 404);
        }

        $data = $request->validated();
        $updated = $this->channelAction->updateChannelProfile($channel->profile, $data);

        return response()->json([
            'success' => true,
            'message' => __('channel.updated_successfully'),
            'data' => $updated,
        ]);
    }

    /**
     * Supprime un channel — réservé au propriétaire (voir DeleteChannelRequest::authorize()).
     */
    public function destroy(DeleteChannelRequest $request, int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (! $channel) {
            return response()->json([
                'success' => false,
                'message' => __('channel.not_found'),
            ], 404);
        }

        $this->channelAction->deleteChannel($channel);

        return response()->json([
            'success' => true,
            'message' => __('channel.deleted_successfully'),
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
            'data' => $channels,
        ]);
    }

    /**
     * Annuaire « v2 » des chaînes (page /chaines) : recherche, facettes type /
     * thème / rythme, tri, chaîne du mois et stats. Réponse alignée sur le
     * catalogue articles/statsdata (data / meta / facets / stats / featured).
     */
    public function catalog(Request $request)
    {
        $userId = $request->user('api')?->id;
        $filters = $request->only(['q', 'kind', 'category', 'pace', 'sort', 'verified', 'followed', 'per_page']);

        $cacheKey = 'channels.catalog.'.md5(json_encode($filters).'|u'.($userId ?? 'guest'));
        $payload = Cache::remember($cacheKey, 60, fn () => $this->channelCatalogAction->getCatalog($filters, $userId));

        return response()->json(['success' => true, 'data' => $payload]);
    }

    /**
     * Liste les channels de l'utilisateur connecté
     */
    public function myChannels(Request $request)
    {
        $perPage = $request->get('per_page', 50);
        $user = Auth::user();

        if (! $user) {
            return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
        }

        $channels = $this->channelAction->getChannelsForUser($user->id, $perPage);

        return response()->json([
            'success' => true,
            'data' => $channels,
        ]);
    }

    /**
     * Liste les membres de l'équipe (owner, admin, redactor, guest)
     */
    public function members(int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (! $channel) {
            return response()->json(['success' => false, 'message' => __('channel.not_found')], 404);
        }

        $members = $channel->managementTeam()
            ->with('profile')
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => trim(($user->profile?->first_name ?? '').' '.($user->profile?->last_name ?? '')) ?: $user->email,
                'avatar' => $user->profile?->avatar ?? null,
                'role' => $user->pivot->role,
                'permissions' => $user->pivot->permissions ?? [],
                'joined_at' => $user->pivot->created_at,
            ]);

        return response()->json(['success' => true, 'data' => $members]);
    }

    /**
     * Invite plusieurs adresses e-mail à rejoindre l'équipe de la chaîne, avec un
     * rôle et des permissions donnés. Réservé aux membres ayant la permission
     * `team.invite_members` (owner/admin l'ont toujours) — voir InviteChannelMembersRequest.
     */
    public function inviteMembers(InviteChannelMembersRequest $request, int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (! $channel) {
            return response()->json(['success' => false, 'message' => __('channel.not_found')], 404);
        }

        $result = $this->channelInvitationAction->invite(
            $channel,
            $request->user(),
            $request->validated('emails'),
            $request->validated('role'),
            $request->validated('permissions') ?? [],
        );

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * Liste les invitations en attente de la chaîne. Même gate que l'invitation.
     */
    public function invitations(Request $request, int $id)
    {
        $user = $request->user();
        $canView = $user && (
            ChannelUser::userIsOwner($id, $user->id)
            || ChannelUser::userHasPermission($id, $user->id, 'team.invite_members')
        );

        if (! $canView) {
            return response()->json(['success' => false, 'message' => "Vous n'avez pas la permission de voir les invitations."], 403);
        }

        $invitations = ChannelInvitation::where('channel_id', $id)
            ->pending()
            ->with('invitedBy.profile')
            ->latest()
            ->get()
            ->map(fn (ChannelInvitation $invitation) => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'permissions' => $invitation->permissions ?? [],
                'invited_by_name' => trim((
                    ($invitation->invitedBy?->profile?->first_name ?? '').' '.($invitation->invitedBy?->profile?->last_name ?? '')
                )) ?: $invitation->invitedBy?->email,
                'created_at' => $invitation->created_at,
                'expires_at' => $invitation->expires_at,
            ]);

        return response()->json(['success' => true, 'data' => $invitations]);
    }

    /**
     * Révoque une invitation en attente. Même gate que l'invitation.
     */
    public function revokeInvitation(Request $request, int $id, int $invitationId)
    {
        $user = $request->user();
        $canRevoke = $user && (
            ChannelUser::userIsOwner($id, $user->id)
            || ChannelUser::userHasPermission($id, $user->id, 'team.invite_members')
        );

        if (! $canRevoke) {
            return response()->json(['success' => false, 'message' => "Vous n'avez pas la permission de révoquer cette invitation."], 403);
        }

        $invitation = ChannelInvitation::where('channel_id', $id)->find($invitationId);

        if (! $invitation) {
            return response()->json(['success' => false, 'message' => 'Invitation introuvable.'], 404);
        }

        $this->channelInvitationAction->revoke($invitation);

        return response()->json(['success' => true]);
    }

    /**
     * Statistiques du dashboard (vues 30j, croissance abonnés, taille de l'équipe)
     */
    public function stats(int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (! $channel) {
            return response()->json(['success' => false, 'message' => __('channel.not_found')], 404);
        }

        return response()->json(['success' => true, 'data' => $this->channelStatsAction->getStats($channel)]);
    }

    /**
     * Jeux de données rattachés aux contenus publiés au nom de la chaîne, avec
     * leur fraîcheur et les contenus qui les utilisent. Alimente l'onglet
     * « Sources de données » du dashboard chaîne.
     */
    public function dataSources(int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (! $channel) {
            return response()->json(['success' => false, 'message' => __('channel.not_found')], 404);
        }

        return response()->json(['success' => true, 'data' => $this->channelDataSourcesAction->getDataSources($channel)]);
    }

    /**
     * Enregistre une vue publique de la chaîne (appelé depuis la page publique)
     */
    public function recordView(int $id)
    {
        $channel = $this->channelAction->getChannelById($id);

        if (! $channel) {
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

        if (! $channel) {
            return response()->json(['success' => false, 'message' => __('channel.not_found')], 404);
        }

        $perPage = $request->get('per_page', 20);

        $subscribers = $channel->subscribers()
            ->with('profile')
            ->paginate($perPage)
            ->through(fn ($user) => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => trim(($user->profile?->first_name ?? '').' '.($user->profile?->last_name ?? '')) ?: $user->email,
                'avatar' => $user->profile?->avatar ?? null,
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

        if (! $channel) {
            return response()->json(['success' => false, 'message' => __('channel.not_found')], 404);
        }

        $result = $this->toggleChannelFollowAction->execute($channel, $request->user()->id);

        return response()->json(['success' => true, 'data' => $result]);
    }
}
