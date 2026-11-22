<?php

declare(strict_types=1);

namespace App\Infrastructure\Resilience;

/**
 * Статистика работы Circuit Breaker.
 */
final class CircuitStatistics
{
    public function __construct(
        public readonly int $totalCalls = 0,
        public readonly int $successfulCalls = 0,
        public readonly int $failedCalls = 0,
        public readonly int $fallbackCalls = 0,
        public readonly int $consecutiveFailures = 0,
        public readonly int $consecutiveSuccesses = 0,
        public readonly ?\DateTimeImmutable $lastFailureTime = null,
        public readonly ?\DateTimeImmutable $lastSuccessTime = null,
        public readonly ?\DateTimeImmutable $lastStateChangeTime = null,
    ) {
    }

    public function getFailureRate(): float
    {
        if ($this->totalCalls === 0) {
            return 0.0;
        }

        return round(($this->failedCalls / $this->totalCalls) * 100, 2);
    }

    public function getSuccessRate(): float
    {
        if ($this->totalCalls === 0) {
            return 0.0;
        }

        return round(($this->successfulCalls / $this->totalCalls) * 100, 2);
    }
}
