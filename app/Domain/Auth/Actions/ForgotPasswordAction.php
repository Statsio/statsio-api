<?php

namespace App\Domain\Auth\Actions;

use App\Models\User\User;

class ForgotPasswordAction
{
    public function __construct(
        private readonly SendPasswordResetEmailAction $sendPasswordResetEmailAction,
    ) {}

    public function execute(string $email): void
    {
        $user = User::where('email', $email)->first();

        // Respond without error even if the email doesn't exist to prevent
        // user enumeration attacks.
        if (! $user) {
            return;
        }

        $this->sendPasswordResetEmailAction->execute($user);
    }
}
