<?php

declare(strict_types=1);

namespace App\Domain\User\ValueObject;

/**
 * ValueObject для пароля пользователя.
 */
final class Password
{
    private const MIN_LENGTH = 8;

    private function __construct(
        private readonly string $hash
    ) {
    }

    public static function fromPlain(string $plainPassword): self
    {
        if (\strlen($plainPassword) < self::MIN_LENGTH) {
            throw new \InvalidArgumentException(\sprintf('Password must be at least %d characters', self::MIN_LENGTH));
        }

        return new self(password_hash($plainPassword, \PASSWORD_ARGON2ID));
    }

    public static function fromHash(string $hash): self
    {
        return new self($hash);
    }

    public function verify(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->hash);
    }

    public function hash(): string
    {
        return $this->hash;
    }

    public function equals(self $other): bool
    {
        return $this->hash === $other->hash;
    }
}
