<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\DTOs\AuthTokenDTO;
use App\Domain\Auth\Exceptions\InvalidVerificationCodeException;
use App\Models\Auth\EmailVerificationToken;
use App\Models\User\User;

class VerifyEmailAction
{
    public function __construct(
        private readonly IssueAuthTokensAction $issueAuthTokensAction,
    ) {}

    public function execute(string $email, string $code): AuthTokenDTO
    {
        $user = User::where('email', $email)->firstOrFail();

        $token = EmailVerificationToken::where('user_id', $user->id)
            ->where('code', $code)
            ->first();

        if (! $token || $token->isExpired()) {
            throw new InvalidVerificationCodeException();
        }

        $user->update(['email_verified_at' => now()]);
        $token->delete();

        return $this->issueAuthTokensAction->execute($user->fresh('profile'));
    }
}
