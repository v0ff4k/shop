<?php

declare(strict_types=1);

namespace App\Infrastructure\Resilience;

/**
 * Фабрика для создания Circuit Breaker.
 */
final class CircuitBreakerFactory
{
    /** @var array<string, CircuitBreakerInterface> */
    private array $circuitBreakers = [];

    public function __construct(
        private readonly \Redis $redis,
        private readonly ?LoggerInterface $logger = null,
        private readonly int $defaultFailureThreshold = 5,
        private readonly int $defaultSuccessThreshold = 3,
        private readonly int $defaultOpenTimeout = 30
    ) {
    }

    /**
     * Получить или создать Circuit Breaker для сервиса.
     */
    public function get(string $serviceName): CircuitBreakerInterface
    {
        if (!isset($this->circuitBreakers[$serviceName])) {
            $this->circuitBreakers[$serviceName] = new RedisCircuitBreaker(
                $this->redis,
                $serviceName,
                $this->defaultFailureThreshold,
                $this->defaultSuccessThreshold,
                $this->defaultOpenTimeout,
                $this->logger
            );
        }

        return $this->circuitBreakers[$serviceName];
    }

    /**
     * Создать Circuit Breaker с кастомными настройками.
     */
    public function create(
        string $serviceName,
        int $failureThreshold = 5,
        int $successThreshold = 3,
        int $openTimeout = 30
    ): CircuitBreakerInterface {
        return new RedisCircuitBreaker(
            $this->redis,
            $serviceName,
            $failureThreshold,
            $successThreshold,
            $openTimeout,
            $this->logger
        );
    }

    /**
     * Получить статистику по всем Circuit Breaker.
     */
    public function getAllStatistics(): array
    {
        $statistics = [];
        foreach ($this->circuitBreakers as $name => $circuitBreaker) {
            $statistics[$name] = $circuitBreaker->getStatistics();
        }

        return $statistics;
    }

    /**
     * Сбросить все Circuit Breaker.
     */
    public function resetAll(): void
    {
        foreach ($this->circuitBreakers as $circuitBreaker) {
            $circuitBreaker->reset();
        }
    }
}
