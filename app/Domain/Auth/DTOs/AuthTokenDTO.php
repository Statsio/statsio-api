<?php

namespace App\Domain\Auth\DTOs;

class AuthTokenDTO
{
    public function __construct(
        public readonly string $accessToken,
        public readonly ?string $refreshToken = null,
        public readonly string $type = 'Bearer',
        public readonly ?int $expiresIn = null,
        public readonly ?array $user = null
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'token' => $this->accessToken,
            'access_token' => $this->accessToken,
            'refresh_token' => $this->refreshToken,
            'type' => $this->type,
            'expires_in' => $this->expiresIn,
            'user' => $this->user,
        ], fn ($value) => $value !== null);
    }
}
