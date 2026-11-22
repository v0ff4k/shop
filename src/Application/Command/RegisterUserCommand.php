<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Команда для регистрации пользователя.
 */
final class RegisterUserCommand
{
    public function __construct(
        public readonly string $username,
        public readonly string $email,
        public readonly string $plainPassword,
        public readonly ?string $fullName = null,
        public readonly ?string $address = null,
        public readonly ?string $additionalInfo = null,
        public readonly array $roles = ['ROLE_CUSTOMER']
    ) {
    }
}
