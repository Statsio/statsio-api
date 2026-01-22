<?php

namespace App\Domain\Auth\DTOs;

class AuthTokenDTO
{
    public function __construct(
        public readonly string $token,
        public readonly string $type = 'Bearer',
        public readonly ?int $expiresIn = null
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'token' => $this->token,
            'type' => $this->type,
            'expires_in' => $this->expiresIn,
        ]);
    }
}
