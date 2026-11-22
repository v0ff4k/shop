<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shop\ValueObject;

use App\Domain\Shop\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * Тесты для ValueObject Money.
 */
final class MoneyTest extends TestCase
{
    public function testCreateFromInt(): void
    {
        $money = Money::fromInt(1000, 'USD');

        $this->assertSame(1000, $money->amount());
        $this->assertSame(10.0, $money->amountFloat());
        $this->assertSame('USD', $money->currency());
    }

    public function testCreateFromFloat(): void
    {
        $money = Money::fromFloat(99.99, 'RUB');

        $this->assertSame(9999, $money->amount());
        $this->assertSame(99.99, $money->amountFloat());
        $this->assertSame('RUB', $money->currency());
    }

    public function testCreateFromString(): void
    {
        $money = Money::fromString('123.45', 'EUR');

        $this->assertSame(12345, $money->amount());
        $this->assertSame(123.45, $money->amountFloat());
        $this->assertSame('EUR', $money->currency());
    }

    public function testAdd(): void
    {
        $money1 = Money::fromFloat(100.00, 'USD');
        $money2 = Money::fromFloat(50.00, 'USD');

        $result = $money1->add($money2);

        $this->assertSame(15000, $result->amount());
        $this->assertSame(150.00, $result->amountFloat());
    }

    public function testAddWithDifferentCurrencies(): void
    {
        $money1 = Money::fromFloat(100.00, 'USD');
        $money2 = Money::fromFloat(50.00, 'EUR');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot add money with different currencies');

        $money1->add($money2);
    }

    public function testSubtract(): void
    {
        $money1 = Money::fromFloat(100.00, 'USD');
        $money2 = Money::fromFloat(30.00, 'USD');

        $result = $money1->subtract($money2);

        $this->assertSame(7000, $result->amount());
        $this->assertSame(70.00, $result->amountFloat());
    }

    public function testSubtractMoreThanAvailable(): void
    {
        $money1 = Money::fromFloat(50.00, 'USD');
        $money2 = Money::fromFloat(100.00, 'USD');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Result cannot be negative');

        $money1->subtract($money2);
    }

    public function testMultiply(): void
    {
        $money = Money::fromFloat(10.00, 'USD');
        $result = $money->multiply(5);

        $this->assertSame(5000, $result->amount());
        $this->assertSame(50.00, $result->amountFloat());
    }

    public function testEquals(): void
    {
        $money1 = Money::fromFloat(100.00, 'USD');
        $money2 = Money::fromFloat(100.00, 'USD');
        $money3 = Money::fromFloat(100.00, 'EUR');

        $this->assertTrue($money1->equals($money2));
        $this->assertFalse($money1->equals($money3));
    }

    public function testToString(): void
    {
        $money = Money::fromFloat(99.99, 'USD');

        $this->assertSame('99.99 USD', (string) $money);
    }

    public function testNegativeAmount(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount cannot be negative');

        Money::fromInt(-100);
    }

    public function testInvalidCurrency(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency must be a 3-letter ISO code');

        Money::fromInt(100, 'US');
    }
}
