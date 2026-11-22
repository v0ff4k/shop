<?php

declare(strict_types=1);

namespace App\Domain\User\Event;

use App\Domain\User\Entity\User;

/**
 * Событие: пользователь зарегистрирован.
 */
final class UserRegisteredEvent
{
    /**
     * @param string[] $roles
     */
    public function __construct(
        public readonly ?int $userId,
        public readonly string $username,
        public readonly string $email,
        public readonly array $roles,
        public readonly \DateTimeImmutable $occurredOn,
    ) {
    }

    public static function fromUser(User $user): self
    {
        return new self(
            userId: $user->getId(),
            username: $user->getUsername(),
            email: $user->getEmail(),
            roles: $user->getRoles(),
            occurredOn: new \DateTimeImmutable()
        );
    }
}
