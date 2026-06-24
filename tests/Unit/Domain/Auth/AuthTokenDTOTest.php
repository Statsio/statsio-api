<?php

namespace Tests\Unit\Domain\Auth;

use App\Domain\Auth\DTOs\AuthTokenDTO;
use PHPUnit\Framework\TestCase;

class AuthTokenDTOTest extends TestCase
{
    public function test_to_array_returns_all_fields_when_set(): void
    {
        $dto = new AuthTokenDTO(
            accessToken: 'access-123',
            refreshToken: 'refresh-456',
            expiresIn: 900,
            user: ['id' => 1, 'email' => 'user@example.com'],
        );

        $array = $dto->toArray();

        $this->assertSame('access-123', $array['token']);
        $this->assertSame('access-123', $array['access_token']);
        $this->assertSame('refresh-456', $array['refresh_token']);
        $this->assertSame('Bearer', $array['type']);
        $this->assertSame(900, $array['expires_in']);
        $this->assertSame(['id' => 1, 'email' => 'user@example.com'], $array['user']);
    }

    public function test_to_array_omits_null_fields(): void
    {
        $dto = new AuthTokenDTO(accessToken: 'access-only');

        $array = $dto->toArray();

        $this->assertArrayHasKey('token', $array);
        $this->assertArrayNotHasKey('refresh_token', $array);
        $this->assertArrayNotHasKey('expires_in', $array);
        $this->assertArrayNotHasKey('user', $array);
    }

    public function test_default_type_is_bearer(): void
    {
        $dto = new AuthTokenDTO(accessToken: 'tok');

        $this->assertSame('Bearer', $dto->type);
    }
}
