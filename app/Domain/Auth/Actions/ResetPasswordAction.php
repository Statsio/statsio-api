<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Exceptions\InvalidResetTokenException;
use App\Models\Auth\PasswordResetToken;
use App\Models\User\User;
use Illuminate\Support\Facades\Hash;

class ResetPasswordAction
{
    public function execute(string $email, string $token, string $password): void
    {
        $user = User::where('email', $email)->firstOrFail();

        $resetToken = PasswordResetToken::where('user_id', $user->id)
            ->where('token', hash('sha256', $token))
            ->first();

        if (! $resetToken || $resetToken->isExpired()) {
            throw new InvalidResetTokenException;
        }

        $user->update(['password' => Hash::make($password)]);

        $resetToken->delete();

        // Révoque toutes les sessions actives de l'utilisateur.
        $user->tokens()->delete();
    }
}
