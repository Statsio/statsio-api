<?php

namespace App\Http\Controllers\Api\Identity;

use App\Domain\Identity\Actions\HandleDiditWebhookAction;
use App\Domain\Identity\Actions\StartIdentityVerificationAction;
use App\Domain\Identity\Support\DiditWebhookSignature;
use App\Http\Controllers\Controller;
use App\Models\Identity\IdentityVerification;
use App\Services\Identity\DiditApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IdentityVerificationController extends Controller
{
    /**
     * POST /identity/verification/start — crée (ou reprend) une session Didit et
     * renvoie l'URL vers laquelle rediriger l'utilisateur.
     */
    public function start(Request $request, StartIdentityVerificationAction $action, DiditApiClient $didit): JsonResponse
    {
        if (! $didit->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => "La vérification d'identité n'est pas disponible pour le moment.",
            ], 503);
        }

        $data = $request->validate([
            'return_path' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $action->execute($request->user(), $data['return_path'] ?? null);

        return response()->json(['success' => true, 'data' => $result]);
    }

    /**
     * GET /identity/verification/status — état de la vérification du compte
     * connecté (utilisé par la page de retour et la page sondage).
     */
    public function status(Request $request, DiditApiClient $didit, HandleDiditWebhookAction $handler): JsonResponse
    {
        $user = $request->user();

        /** @var IdentityVerification|null $latest */
        $latest = $user->identityVerifications()->latest()->first();

        // Repli si un webhook a été manqué : rafraîchit une session non terminale figée.
        if ($latest && $latest->isPending() && $didit->isConfigured()
            && ($latest->last_event_at === null || $latest->last_event_at->lt(now()->subMinutes(2)))) {
            $decision = $didit->getSessionDecision($latest->didit_session_id);

            if (is_array($decision) && isset($decision['status'])) {
                $handler->apply($latest, (string) $decision['status']);
                $latest->refresh();
                $user->refresh();
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'status' => $latest?->status?->value ?? $latest?->getRawOriginal('status'),
                'verified' => $user->hasVerifiedIdentity(),
                'verified_at' => $user->identity_verified_at,
            ],
        ]);
    }

    /**
     * POST /identity/verification/webhook — endpoint public appelé par Didit.
     * Signature HMAC vérifiée en premier ; traitement synchrone (une écriture).
     */
    public function webhook(Request $request, HandleDiditWebhookAction $action): JsonResponse
    {
        $valid = DiditWebhookSignature::isValid(
            $request->getContent(),
            $request->header('X-Signature'),
            $request->header('X-Timestamp'),
        );

        abort_unless($valid, 401, 'Signature du webhook Didit invalide.');

        $action->execute((array) $request->json()->all());

        return response()->json(['received' => true]);
    }
}
