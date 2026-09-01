<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Enums\IdentityVerificationStatusEnum;
use App\Domain\Identity\Exceptions\DiditException;
use App\Models\User\User;
use App\Services\Identity\DiditApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class StartIdentityVerificationAction
{
    public function __construct(private readonly DiditApiClient $didit) {}

    /**
     * Démarre (ou reprend) une vérification d'identité pour le compte.
     *
     * @return array{url: string|null, status: string, verified: bool}
     */
    public function execute(User $user, ?string $returnPath = null): array
    {
        if (! $this->didit->isConfigured()) {
            throw DiditException::notConfigured();
        }

        if ($user->hasVerifiedIdentity()) {
            return ['url' => null, 'status' => IdentityVerificationStatusEnum::Approved->value, 'verified' => true];
        }

        // Mode dev : on approuve tout de suite, sans redirection ni appel réseau.
        if ($this->didit->isFake()) {
            $user->identityVerifications()->create([
                'didit_session_id' => 'fake-'.Str::uuid(),
                'status' => IdentityVerificationStatusEnum::Approved->value,
                'workflow_id' => 'fake',
                'verified_at' => now(),
                'last_event_at' => now(),
            ]);
            $user->forceFill(['identity_verified_at' => now()])->save();

            return ['url' => null, 'status' => IdentityVerificationStatusEnum::Approved->value, 'verified' => true];
        }

        // Sérialise les démarrages concurrents d'un même compte (double-clic, deux onglets) :
        // sans ce verrou, deux requêtes passent la vérification « session en cours » puis
        // tentent d'insérer la même session Didit — violation de contrainte unique
        // (Didit renvoie la session déjà ouverte pour un `vendor_data` donné).
        return Cache::lock('identity-verification:start:'.$user->id, 30)->block(15, function () use ($user, $returnPath) {
            // Reprend une session encore ouverte (< 1 h) au lieu d'en facturer une nouvelle.
            $pending = $user->identityVerifications()
                ->where('created_at', '>', now()->subHour())
                ->whereNotNull('session_url')
                ->latest()
                ->first();

            if ($pending && $pending->isPending()) {
                return ['url' => $pending->session_url, 'status' => $pending->status->value, 'verified' => false];
            }

            $session = $this->didit->createSession(
                vendorData: (string) $user->id,
                callbackUrl: $this->callbackUrl($returnPath),
            );

            // `updateOrCreate` sur `didit_session_id` : si Didit renvoie une session
            // déjà connue (reprise), on la met à jour au lieu de dupliquer la ligne.
            $user->identityVerifications()->updateOrCreate(
                ['didit_session_id' => $session['session_id']],
                [
                    'didit_session_number' => $session['session_number'],
                    'status' => $session['status'],
                    'workflow_id' => config('services.didit.workflow_id'),
                    'session_url' => $session['url'],
                ],
            );

            return ['url' => $session['url'], 'status' => $session['status'], 'verified' => false];
        });
    }

    private function callbackUrl(?string $returnPath): string
    {
        $base = rtrim((string) (config('services.didit.callback_base_url') ?: config('app.frontend_url')), '/');
        $url = $base.'/identity/callback';

        $returnPath = $this->sanitizeReturnPath($returnPath);

        return $returnPath ? $url.'?return='.rawurlencode($returnPath) : $url;
    }

    /** N'accepte qu'un chemin relatif interne (« /sondages/xxx »), jamais une URL absolue. */
    private function sanitizeReturnPath(?string $returnPath): ?string
    {
        if ($returnPath === null || ! Str::startsWith($returnPath, '/') || Str::startsWith($returnPath, '//')) {
            return null;
        }

        return Str::limit($returnPath, 500, '');
    }
}
