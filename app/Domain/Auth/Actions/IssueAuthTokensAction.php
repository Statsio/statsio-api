<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\DTOs\AuthTokenDTO;
use App\Models\Auth\RefreshToken;
use App\Models\User\User;
use Illuminate\Support\Str;

class IssueAuthTokensAction
{
    public function execute(User $user): AuthTokenDTO
    {
        $accessTokenTtlMinutes = (int) config('sanctum.expiration', 15);
        $refreshTokenTtlDays = (int) config('auth.refresh_token_ttl_days', 30);

        $newAccessToken = $user->createToken(
            'api-token',
            ['*'],
            now()->addMinutes($accessTokenTtlMinutes)
        );

        $plainRefreshToken = Str::random(80);

        RefreshToken::create([
            'user_id' => $user->id,
            'personal_access_token_id' => $newAccessToken->accessToken->id,
            'token' => hash('sha256', $plainRefreshToken),
            'expires_at' => now()->addDays($refreshTokenTtlDays),
        ]);

        return new AuthTokenDTO(
            accessToken: $newAccessToken->plainTextToken,
            refreshToken: $plainRefreshToken,
            expiresIn: $accessTokenTtlMinutes * 60,
            user: [
                'id' => $user->id,
                'email' => $user->email,
                'is_admin' => (bool) $user->is_admin,
                'status' => $user->status,
                'email_verified_at' => $user->email_verified_at,
                'profile' => $user->profile,
            ]
        );
    }
}
