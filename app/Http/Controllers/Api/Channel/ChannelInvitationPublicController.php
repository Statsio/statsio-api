<?php

namespace App\Http\Controllers\Api\Channel;

use App\Domain\Channel\Actions\ChannelInvitationAction;
use App\Domain\Channel\Enums\ChannelUserRoleEnum;
use App\Domain\Content\Support\PremiumLimits;
use App\Http\Controllers\Controller;
use App\Models\Channel\ChannelUser;
use Illuminate\Http\Request;

class ChannelInvitationPublicController extends Controller
{
    public function __construct(
        private ChannelInvitationAction $channelInvitationAction
    ) {}

    /**
     * Lecture publique d'une invitation par son token (avant connexion) — pour
     * afficher "X vous invite à rejoindre Y" sans exposer de données sensibles.
     */
    public function show(string $token)
    {
        $invitation = $this->channelInvitationAction->findByToken($token);

        if (! $invitation) {
            return response()->json(['success' => false, 'message' => 'Invitation introuvable.'], 404);
        }

        $channel = $invitation->channel()->with('profile')->first();
        $roleEnum = ChannelUserRoleEnum::tryFrom($invitation->role);

        return response()->json([
            'success' => true,
            'data' => [
                'channel_id' => $invitation->channel_id,
                'channel_name' => $channel?->profile?->name,
                'channel_logo' => $channel?->profile?->logo_url,
                'role' => $invitation->role,
                'role_label' => $roleEnum?->getDisplayName() ?? $invitation->role,
                'email' => $invitation->email,
                'status' => $invitation->status,
                'expired' => $invitation->status === 'pending' && $invitation->isExpired(),
            ],
        ]);
    }

    /**
     * Accepte une invitation — nécessite d'être connecté avec le compte dont
     * l'e-mail correspond à l'invitation (voir ChannelInvitationAction::accept()).
     */
    public function accept(Request $request, string $token)
    {
        $invitation = $this->channelInvitationAction->findByToken($token);
        $channel = $invitation?->status === 'pending' ? $invitation->channel : null;

        // Re-vérifié à l'acceptation (pas seulement à l'envoi) : plusieurs invitations
        // envoyées avant d'atteindre la limite peuvent être acceptées après coup.
        $maxMembers = $channel ? PremiumLimits::offerForChannelOwner($channel)?->max_channel_members : null;
        if ($channel && $maxMembers !== null) {
            $teamRoles = array_map(fn ($r) => $r->value, ChannelUserRoleEnum::getManagementRoles());
            $currentMembers = ChannelUser::where('channel_id', $channel->id)
                ->whereIn('role', $teamRoles)
                ->count();

            if ($currentMembers >= $maxMembers) {
                return response()->json([
                    'success' => false,
                    'message' => __('errors.premium_channel_members_limit', ['max' => $maxMembers]),
                ], 403);
            }
        }

        try {
            $channelUser = $this->channelInvitationAction->accept($token, $request->user());
        } catch (\RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Invitation acceptée.',
            'data' => ['channel_id' => $channelUser->channel_id],
        ]);
    }
}
