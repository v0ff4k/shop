<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

/**
 * ValueObject для идентификатора пользователя.
 */
final class UserId
{
    private function __construct(
        private readonly int $id
    ) {
        if ($id <= 0) {
            throw new \InvalidArgumentException('User ID must be positive');
        }
    }

    public function __toString(): string
    {
        return (string) $this->id;
    }

    public static function fromInt(int $id): self
    {
        return new self($id);
    }

    public function value(): int
    {
        return $this->id;
    }

    public function equals(self $other): bool
    {
        return $this->id === $other->id;
    }
}
