<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\DTOs\AuthTokenDTO;
use App\Domain\Auth\Exceptions\InvalidRefreshTokenException;
use App\Models\Auth\RefreshToken;

class RefreshTokenAction
{
    public function __construct(
        private readonly IssueAuthTokensAction $issueAuthTokensAction
    ) {}

    public function execute(string $plainRefreshToken): AuthTokenDTO
    {
        $refreshToken = RefreshToken::query()
            ->with(['user.profile', 'personalAccessToken'])
            ->where('token', hash('sha256', $plainRefreshToken))
            ->first();

        if (
            !$refreshToken
            || $refreshToken->revoked_at
            || $refreshToken->expires_at->isPast()
            || !$refreshToken->user
        ) {
            throw new InvalidRefreshTokenException();
        }

        $refreshToken->update([
            'last_used_at' => now(),
            'revoked_at' => now(),
        ]);

        $refreshToken->personalAccessToken?->delete();

        return $this->issueAuthTokensAction->execute($refreshToken->user->fresh('profile'));
    }
}
