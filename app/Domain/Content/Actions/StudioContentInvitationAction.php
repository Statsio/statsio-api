<?php

namespace App\Domain\Content\Actions;

use App\Domain\Content\Support\StudioContentAccess;
use App\Mail\Studio\StudioContentInvitationMailable;
use App\Models\Studio\StudioContentCollaborator;
use App\Models\Studio\StudioContentInvitation;
use App\Models\StudioContent;
use App\Models\User\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class StudioContentInvitationAction
{
    /**
     * @param  string[]  $emails
     * @param  array<string, string>  $requestedPermissions
     * @return array{created: string[], resent: string[], skipped: array<array{email: string, reason: string}>}
     */
    public function invite(StudioContent $content, User $inviter, array $emails, array $requestedPermissions): array
    {
        $permissions = StudioContentAccess::normalizePermissions($requestedPermissions);

        if (! StudioContentAccess::hasAnyNonNone($permissions)) {
            throw new \InvalidArgumentException('Au moins une permission lecture ou modification est requise.');
        }

        $created = [];
        $resent = [];
        $skipped = [];

        foreach ($this->normalizeEmails($emails) as $email) {
            if (mb_strtolower($content->user?->email ?? '') === $email) {
                $skipped[] = ['email' => $email, 'reason' => 'C\'est déjà le propriétaire du contenu.'];

                continue;
            }

            $existingUser = User::where('email', $email)->first();

            if ($existingUser && StudioContentCollaborator::where('studio_content_id', $content->id)
                ->where('user_id', $existingUser->id)
                ->exists()) {
                $skipped[] = ['email' => $email, 'reason' => 'Déjà collaborateur sur ce contenu.'];

                continue;
            }

            $pending = StudioContentInvitation::where('studio_content_id', $content->id)
                ->where('email', $email)
                ->pending()
                ->first();

            $plainToken = Str::random(64);

            if ($pending) {
                $pending->update([
                    'permissions' => $permissions,
                    'token' => hash('sha256', $plainToken),
                    'invited_by' => $inviter->id,
                    'expires_at' => now()->addDays(7),
                ]);
                $resent[] = $email;
            } else {
                StudioContentInvitation::create([
                    'studio_content_id' => $content->id,
                    'email' => $email,
                    'permissions' => $permissions,
                    'token' => hash('sha256', $plainToken),
                    'invited_by' => $inviter->id,
                    'status' => 'pending',
                    'expires_at' => now()->addDays(7),
                ]);
                $created[] = $email;
            }

            $this->sendInvitationMail($content, $inviter, $email, $plainToken);
        }

        return ['created' => $created, 'resent' => $resent, 'skipped' => $skipped];
    }

    public function accept(string $plainToken, User $user): StudioContentCollaborator
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

        if ((int) $invitation->studio_content_id && (int) $invitation->content?->user_id === (int) $user->id) {
            throw new \RuntimeException('Vous êtes déjà propriétaire de ce contenu.');
        }

        $collaborator = StudioContentCollaborator::updateOrCreate(
            [
                'studio_content_id' => $invitation->studio_content_id,
                'user_id' => $user->id,
            ],
            [
                'permissions' => StudioContentAccess::normalizePermissions($invitation->permissions ?? []),
                'invited_by' => $invitation->invited_by,
            ],
        );

        $invitation->update(['status' => 'accepted', 'accepted_at' => now()]);

        return $collaborator;
    }

    public function revoke(StudioContentInvitation $invitation): void
    {
        $invitation->update(['status' => 'revoked']);
    }

    public function findByToken(string $plainToken): ?StudioContentInvitation
    {
        return StudioContentInvitation::with(['content', 'invitedBy.profile'])
            ->where('token', hash('sha256', $plainToken))
            ->first();
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

    private function sendInvitationMail(StudioContent $content, User $inviter, string $email, string $plainToken): void
    {
        $frontendUrl = rtrim(config('app.frontend_url'), '/');
        $inviterName = trim(($inviter->profile?->first_name ?? '').' '.($inviter->profile?->last_name ?? ''))
            ?: $inviter->email;

        Mail::to($email)->send(new StudioContentInvitationMailable(
            contentTitle: $content->title ?: 'un contenu Statsio',
            inviterName: $inviterName,
            acceptUrl: $frontendUrl.'/invitations/contenu/'.$plainToken,
        ));
    }
}
