<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Resilience;

use App\Infrastructure\Resilience\CircuitState;
use PHPUnit\Framework\TestCase;

/**
 * Тесты для CircuitState enum.
 */
final class CircuitStateTest extends TestCase
{
    public function testStates(): void
    {
        $this->assertSame('closed', CircuitState::CLOSED->value);
        $this->assertSame('open', CircuitState::OPEN->value);
        $this->assertSame('half_open', CircuitState::HALF_OPEN->value);
    }

    public function testTryFrom(): void
    {
        $this->assertEquals(CircuitState::CLOSED, CircuitState::tryFrom('closed'));
        $this->assertEquals(CircuitState::OPEN, CircuitState::tryFrom('open'));
        $this->assertEquals(CircuitState::HALF_OPEN, CircuitState::tryFrom('half_open'));
        $this->assertNull(CircuitState::tryFrom('invalid'));
    }

    public function testFrom(): void
    {
        $this->assertEquals(CircuitState::CLOSED, CircuitState::from('closed'));
        $this->assertEquals(CircuitState::OPEN, CircuitState::from('open'));
        $this->assertEquals(CircuitState::HALF_OPEN, CircuitState::from('half_open'));
    }

    public function testFromInvalid(): void
    {
        $this->expectException(\ValueError::class);
        CircuitState::from('invalid');
    }
}
