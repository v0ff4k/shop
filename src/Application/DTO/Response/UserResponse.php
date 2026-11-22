<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

use App\Domain\User\Entity\User;

/**
 * DTO для ответа пользователя.
 */
final class UserResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $username,
        public readonly string $email,
        public readonly ?string $fullName,
        public readonly ?string $address,
        public readonly ?string $additionalInfo,
        public readonly array $roles,
        public readonly bool $enabled,
        public readonly \DateTimeImmutable $registeredAt,
        public readonly ?\DateTimeImmutable $lastLoginAt = null
    ) {
    }

    public static function fromEntity(User $user): self
    {
        return new self(
            $user->getId(),
            $user->getUsername(),
            $user->getEmail(),
            $user->getFullName(),
            $user->getAddress(),
            $user->getAdditionalInfo(),
            $user->getRoles(),
            $user->isEnabled(),
            $user->getRegisteredAt(),
            $user->getLastLoginAt()
        );
    }
}
