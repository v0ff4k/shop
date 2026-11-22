<?php

declare(strict_types=1);

namespace App\Infrastructure\Resilience;

/**
 * Сервис для управления Circuit Breaker.
 */
final class CircuitBreakerManager
{
    public function __construct(
        private readonly CircuitBreakerFactory $factory
    ) {
    }

    /**
     * Получить статистику всех Circuit Breaker.
     *
     * @return array<string, array{
     *     state: string,
     *     total_calls: int,
     *     successful_calls: int,
     *     failed_calls: int,
     *     fallback_calls: int,
     *     failure_rate: float,
     *     success_rate: float
     * }>
     */
    public function getAllStatistics(): array
    {
        $statistics = $this->factory->getAllStatistics();
        $result = [];

        foreach ($statistics as $serviceName => $circuitBreaker) {
            $stats = $circuitBreaker->getStatistics();
            $result[$serviceName] = [
                'state' => $circuitBreaker->getState()->value,
                'total_calls' => $stats->totalCalls,
                'successful_calls' => $stats->successfulCalls,
                'failed_calls' => $stats->failedCalls,
                'fallback_calls' => $stats->fallbackCalls,
                'failure_rate' => $stats->getFailureRate(),
                'success_rate' => $stats->getSuccessRate(),
                'consecutive_failures' => $stats->consecutiveFailures,
                'consecutive_successes' => $stats->consecutiveSuccesses,
            ];
        }

        return $result;
    }

    /**
     * Получить состояние конкретного Circuit Breaker.
     */
    public function getState(string $serviceName): string
    {
        $circuitBreaker = $this->factory->get($serviceName);

        return $circuitBreaker->getState()->value;
    }

    /**
     * Сбросить конкретный Circuit Breaker.
     */
    public function reset(string $serviceName): void
    {
        $circuitBreaker = $this->factory->get($serviceName);
        $circuitBreaker->reset();
    }

    /**
     * Сбросить все Circuit Breaker.
     */
    public function resetAll(): void
    {
        $this->factory->resetAll();
    }
}
