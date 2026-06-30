<?php

namespace App\Domain\Auth\Actions;

use App\Models\User\User;

class ResendVerificationEmailAction
{
    public function __construct(
        private readonly SendVerificationEmailAction $sendVerificationEmailAction,
    ) {}

    public function execute(string $email): void
    {
        $user = User::where('email', $email)->first();

        // Respond without error even if email doesn't exist or is already verified
        // to prevent user enumeration attacks.
        if (! $user || $user->email_verified_at !== null) {
            return;
        }

        $this->sendVerificationEmailAction->execute($user);
    }
}
