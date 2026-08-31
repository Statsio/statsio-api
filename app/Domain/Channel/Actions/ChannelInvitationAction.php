<?php

namespace App\Domain\Channel\Actions;

use App\Domain\Channel\Enums\ChannelPermissionEnum;
use App\Domain\Channel\Enums\ChannelUserRoleEnum;
use App\Mail\Channel\ChannelInvitationMailable;
use App\Models\Channel\Channel;
use App\Models\Channel\ChannelInvitation;
use App\Models\Channel\ChannelUser;
use App\Models\User\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ChannelInvitationAction
{
    /**
     * Invite plusieurs adresses e-mail à rejoindre l'équipe d'une chaîne avec un
     * rôle et un jeu de permissions donnés.
     *
     * @param  string[]  $emails
     * @param  string[]  $requestedPermissions
     * @return array{created: string[], resent: string[], skipped: array<array{email: string, reason: string}>}
     */
    public function invite(Channel $channel, User $inviter, array $emails, string $role, array $requestedPermissions): array
    {
        $roleEnum = ChannelUserRoleEnum::from($role);
        $permissions = $this->resolvePermissions($roleEnum, $requestedPermissions);

        $created = [];
        $resent = [];
        $skipped = [];

        foreach ($this->normalizeEmails($emails) as $email) {
            $existingUser = User::where('email', $email)->first();

            if ($existingUser && ChannelUser::where('channel_id', $channel->id)->where('user_id', $existingUser->id)->exists()) {
                $skipped[] = ['email' => $email, 'reason' => 'Déjà membre de la chaîne.'];

                continue;
            }

            $pending = ChannelInvitation::where('channel_id', $channel->id)
                ->where('email', $email)
                ->pending()
                ->first();

            $plainToken = Str::random(64);

            if ($pending) {
                $pending->update([
                    'role' => $roleEnum->value,
                    'permissions' => $permissions,
                    'token' => hash('sha256', $plainToken),
                    'invited_by' => $inviter->id,
                    'expires_at' => now()->addDays(7),
                ]);
                $resent[] = $email;
            } else {
                ChannelInvitation::create([
                    'channel_id' => $channel->id,
                    'email' => $email,
                    'role' => $roleEnum->value,
                    'permissions' => $permissions,
                    'token' => hash('sha256', $plainToken),
                    'invited_by' => $inviter->id,
                    'status' => 'pending',
                    'expires_at' => now()->addDays(7),
                ]);
                $created[] = $email;
            }

            $this->sendInvitationMail($channel, $inviter, $email, $roleEnum, $plainToken);
        }

        return ['created' => $created, 'resent' => $resent, 'skipped' => $skipped];
    }

    public function accept(string $plainToken, User $user): ChannelUser
    {
        $invitation = $this->findByToken($plainToken);

        if (! $invitation || $invitation->status !== 'pending' || $invitation->isExpired()) {
            throw new \RuntimeException('Cette invitation est invalide ou a expiré.');
        }

        if (mb_strtolower($invitation->email) !== mb_strtolower($user->email)) {
            throw new \RuntimeException(
                "Cette invitation est destinée à l'adresse {$invitation->email}, pas à votre compte."
            );
        }

        $channelUser = ChannelUser::updateOrCreate(
            ['channel_id' => $invitation->channel_id, 'user_id' => $user->id],
            [
                'role' => $invitation->role,
                'permissions' => $invitation->permissions,
                'subscribed_at' => now(),
                'notifications_enabled' => true,
            ],
        );

        $invitation->update(['status' => 'accepted', 'accepted_at' => now()]);

        return $channelUser;
    }

    public function revoke(ChannelInvitation $invitation): void
    {
        $invitation->update(['status' => 'revoked']);
    }

    public function findByToken(string $plainToken): ?ChannelInvitation
    {
        return ChannelInvitation::where('token', hash('sha256', $plainToken))->first();
    }

    /**
     * @param  string[]  $emails
     * @return string[]
     */
    private function normalizeEmails(array $emails): array
    {
        $normalized = array_map(fn (string $e) => mb_strtolower(trim($e)), $emails);

        return array_values(array_unique($normalized));
    }

    /**
     * Owner/admin obtiennent toujours le catalogue complet, quoi que le client
     * ait envoyé. Pour les autres rôles, on ne garde que les permissions valides
     * du catalogue (défense en profondeur — la FormRequest valide déjà en amont).
     *
     * @param  string[]  $requested
     * @return string[]
     */
    private function resolvePermissions(ChannelUserRoleEnum $role, array $requested): array
    {
        if (in_array($role, [ChannelUserRoleEnum::OWNER, ChannelUserRoleEnum::ADMIN], true)) {
            return $role->defaultPermissions();
        }

        $valid = ChannelPermissionEnum::values();

        return array_values(array_intersect($requested, $valid));
    }

    private function sendInvitationMail(Channel $channel, User $inviter, string $email, ChannelUserRoleEnum $role, string $plainToken): void
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $inviterName = trim(($inviter->profile?->first_name ?? '').' '.($inviter->profile?->last_name ?? '')) ?: $inviter->email;

        Mail::to($email)->send(new ChannelInvitationMailable(
            channelName: $channel->profile?->name ?? 'une chaîne Statsio',
            roleLabel: $role->getDisplayName(),
            inviterName: $inviterName,
            acceptUrl: $frontendUrl.'/invitations/'.$plainToken,
        ));
    }
}
