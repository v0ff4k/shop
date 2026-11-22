<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Resilience;

use App\Infrastructure\Resilience\CircuitBreakerOpenException;
use PHPUnit\Framework\TestCase;

/**
 * Тесты для CircuitBreakerOpenException.
 */
final class CircuitBreakerOpenExceptionTest extends TestCase
{
    public function testCreateWithServiceName(): void
    {
        $exception = new CircuitBreakerOpenException('payment_gateway');

        $this->assertSame('Circuit Breaker открыт для сервиса: payment_gateway', $exception->getMessage());
        $this->assertSame(503, $exception->getCode());
        $this->assertNull($exception->getPrevious());
    }

    public function testCreateWithPreviousException(): void
    {
        $previous = new \RuntimeException('Previous error');
        $exception = new CircuitBreakerOpenException('delivery_api', $previous);

        $this->assertSame('Circuit Breaker открыт для сервиса: delivery_api', $exception->getMessage());
        $this->assertSame(503, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }

    public function testIsRuntimeException(): void
    {
        $exception = new CircuitBreakerOpenException('test_service');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }
}
