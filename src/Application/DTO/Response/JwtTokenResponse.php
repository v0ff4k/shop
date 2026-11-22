<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

/**
 * DTO для ответа JWT токена.
 */
final class JwtTokenResponse
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly int $expiresIn,
        public readonly string $tokenType = 'Bearer'
    ) {
    }
}
