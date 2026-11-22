<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

/**
 * ValueObject для email адреса.
 */
final class Email
{
    private readonly string $value;

    private function __construct(string $value)
    {
        $normalized = mb_strtolower($value);

        if (!filter_var($normalized, \FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Invalid email address');
        }

        $this->value = $normalized;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}
