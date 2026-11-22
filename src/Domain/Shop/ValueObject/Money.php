<?php

declare(strict_types=1);

namespace App\Domain\Shop\ValueObject;

/**
 * ValueObject для денежной суммы (цены товара).
 */
final class Money
{
    private function __construct(
        private readonly int $amount,
        private readonly string $currency
    ) {
        if ($this->amount < 0) {
            throw new \InvalidArgumentException('Amount cannot be negative');
        }

        if (\strlen($this->currency) !== 3) {
            throw new \InvalidArgumentException('Currency must be a 3-letter ISO code');
        }
    }

    public function __toString(): string
    {
        return \sprintf('%.2f %s', $this->amountFloat(), $this->currency);
    }

    public static function fromInt(int $amount, string $currency = 'USD'): self
    {
        return new self($amount, $currency);
    }

    public static function fromFloat(float $amount, string $currency = 'USD'): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    public static function fromString(string $amount, string $currency = 'USD'): self
    {
        return new self((int) round((float) $amount * 100), $currency);
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }

    public function add(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Cannot add money with different currencies');
        }

        return new self($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new \InvalidArgumentException('Cannot subtract money with different currencies');
        }

        if ($this->amount < $other->amount) {
            throw new \InvalidArgumentException('Result cannot be negative');
        }

        return new self($this->amount - $other->amount, $this->currency);
    }

    public function multiply(int $multiplier): self
    {
        return new self($this->amount * $multiplier, $this->currency);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }

    public function amountFloat(): float
    {
        return $this->amount / 100;
    }
}
