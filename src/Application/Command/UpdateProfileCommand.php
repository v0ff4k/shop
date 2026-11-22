<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Команда для обновления профиля пользователя.
 */
final class UpdateProfileCommand
{
    public function __construct(
        public readonly string $userEmail,
        public readonly ?string $email = null,
        public readonly ?string $fullName = null,
        public readonly ?string $address = null,
        public readonly ?string $additionalInfo = null,
        public readonly ?string $currentPassword = null,
        public readonly ?string $newPassword = null
    ) {
    }
}
