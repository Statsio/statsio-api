<?php

namespace App\Console\Commands;

use App\Models\User\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

use function Laravel\Prompts\password;

/**
 * Définit (ou réinitialise) le mot de passe d'un compte admin et lui donne
 * le drapeau `is_admin`. Utile pour le tout premier accès au back-office
 * Filament, ou pour un admin qui ne s'était connecté que via Google
 * (aucun mot de passe en base) quand l'envoi d'e-mail est indisponible.
 */
class MakeAdminPasswordCommand extends Command
{
    protected $signature = 'app:make-admin-password {email}';

    protected $description = 'Définit le mot de passe et le rôle admin d\'un utilisateur (accès back-office Filament)';

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::withTrashed()->where('email', $email)->first();

        if (! $user) {
            $this->error("Aucun utilisateur avec l'email {$email}.");

            return self::FAILURE;
        }

        $plain = password(
            label: 'Nouveau mot de passe',
            required: true,
            validate: fn (string $value) => strlen($value) < 8
                ? 'Le mot de passe doit faire au moins 8 caractères.'
                : null,
        );

        $user->forceFill([
            'password' => Hash::make($plain),
            'is_admin' => true,
        ])->save();

        $this->info("Mot de passe défini et rôle admin accordé à {$email}.");

        return self::SUCCESS;
    }
}
