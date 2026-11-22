<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Resilience;

use App\Infrastructure\Resilience\CircuitStatistics;
use PHPUnit\Framework\TestCase;

/**
 * Тесты для CircuitStatistics.
 */
final class CircuitStatisticsTest extends TestCase
{
    public function testCreateWithDefaultValues(): void
    {
        $stats = new CircuitStatistics();

        $this->assertSame(0, $stats->totalCalls);
        $this->assertSame(0, $stats->successfulCalls);
        $this->assertSame(0, $stats->failedCalls);
        $this->assertSame(0, $stats->fallbackCalls);
        $this->assertSame(0, $stats->consecutiveFailures);
        $this->assertSame(0, $stats->consecutiveSuccesses);
        $this->assertNull($stats->lastFailureTime);
        $this->assertNull($stats->lastSuccessTime);
    }

    public function testCreateWithValues(): void
    {
        $now = new \DateTimeImmutable();
        $stats = new CircuitStatistics(
            totalCalls: 100,
            successfulCalls: 80,
            failedCalls: 15,
            fallbackCalls: 5,
            consecutiveFailures: 2,
            consecutiveSuccesses: 3,
            lastFailureTime: $now,
            lastSuccessTime: $now
        );

        $this->assertSame(100, $stats->totalCalls);
        $this->assertSame(80, $stats->successfulCalls);
        $this->assertSame(15, $stats->failedCalls);
        $this->assertSame(5, $stats->fallbackCalls);
        $this->assertSame(2, $stats->consecutiveFailures);
        $this->assertSame(3, $stats->consecutiveSuccesses);
        $this->assertSame($now, $stats->lastFailureTime);
        $this->assertSame($now, $stats->lastSuccessTime);
    }

    public function testFailureRate(): void
    {
        $stats = new CircuitStatistics(
            totalCalls: 100,
            failedCalls: 25
        );

        $this->assertSame(25.0, $stats->getFailureRate());
    }

    public function testFailureRateWithZeroCalls(): void
    {
        $stats = new CircuitStatistics();

        $this->assertSame(0.0, $stats->getFailureRate());
    }

    public function testSuccessRate(): void
    {
        $stats = new CircuitStatistics(
            totalCalls: 100,
            successfulCalls: 90
        );

        $this->assertSame(90.0, $stats->getSuccessRate());
    }

    public function testSuccessRateWithZeroCalls(): void
    {
        $stats = new CircuitStatistics();

        $this->assertSame(0.0, $stats->getSuccessRate());
    }

    public function testFailureRateRounding(): void
    {
        $stats = new CircuitStatistics(
            totalCalls: 33,
            failedCalls: 10
        );

        $this->assertSame(30.3, $stats->getFailureRate());
    }
}
