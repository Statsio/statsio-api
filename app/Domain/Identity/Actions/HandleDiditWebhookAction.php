<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\IdentityVerificationStatusEnum;
use App\Models\Identity\IdentityVerification;
use Illuminate\Support\Facades\DB;

class HandleDiditWebhookAction
{
    /**
     * Applique un événement de session Didit (webhook `status.updated` ou repli via
     * GET /decision/). Idempotent : rejouer le même payload ne change rien.
     *
     * @param  array<string, mixed>  $payload
     */
    public function execute(array $payload): void
    {
        $sessionId = $payload['session_id'] ?? ($payload['session']['session_id'] ?? null);
        $status = $payload['status'] ?? ($payload['session']['status'] ?? null);

        if (! is_string($sessionId) || $sessionId === '') {
            return;
        }

        $verification = IdentityVerification::where('didit_session_id', $sessionId)->first();

        if ($verification === null) {
            return;
        }

        $this->apply($verification, is_string($status) ? $status : null);
    }

    /** Réutilisé par la réconciliation (endpoint status). */
    public function apply(IdentityVerification $verification, ?string $rawStatus): void
    {
        $status = IdentityVerificationStatusEnum::tryFromLabel($rawStatus);

        DB::transaction(function () use ($verification, $status) {
            // Statut inconnu de Didit : on garde l'actuel plutôt que d'écrire une valeur
            // que le cast enum ne saurait pas relire.
            $verification->forceFill([
                'status' => $status?->value ?? $verification->getRawOriginal('status'),
                'last_event_at' => now(),
            ]);

            if ($status?->isApproved() && $verification->verified_at === null) {
                $verification->verified_at = now();
            }

            $verification->save();

            // Colonne dénormalisée sur le compte : posée une fois, jamais retirée par un
            // webhook (une identité vérifiée le reste ; « Kyc Expired » sera géré à part si besoin).
            if ($status?->isApproved()) {
                $verification->user()->whereNull('identity_verified_at')
                    ->update(['identity_verified_at' => now()]);
            }
        });
    }
}
