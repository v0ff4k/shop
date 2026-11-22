<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

/**
 * DTO для запроса аутентификации пользователя.
 */
final class LoginRequest
{
    public function __construct(
        public readonly string $username,
        public readonly string $password,
        public readonly bool $rememberMe = false
    ) {
    }
}
