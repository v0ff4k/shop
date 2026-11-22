<?php

declare(strict_types=1);

namespace App\Domain\Shop\ValueObject;

/**
 * ValueObject для идентификатора категории.
 */
final class CategoryId
{
    private function __construct(
        private readonly int $id
    ) {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Category ID must be positive');
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
