<?php

declare(strict_types=1);

namespace App\Infrastructure\Resilience;

use Psr\Log\LoggerInterface;
use Redis;

/**
 * Redis реализация Circuit Breaker.
 *
 * Паттерн Circuit Breaker с тремя состояниями:
 * - CLOSED: нормальная работа, запросы проходят
 * - OPEN: сбой, запросы блокируются, используется fallback
 * - HALF_OPEN: тестовое состояние, один запрос проверяет восстановление
 */
final class RedisCircuitBreaker implements CircuitBreakerInterface
{
    private const KEY_PREFIX = 'circuit_breaker:';

    public function __construct(
        private readonly \Redis $redis,
        private readonly string $serviceName,
        private readonly int $failureThreshold = 5,
        private readonly int $successThreshold = 3,
        private readonly int $openTimeout = 30,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function execute(callable $operation, ?callable $fallback = null): mixed
    {
        $state = $this->getState();

        // Если Circuit открыт - блокируем запрос
        if ($state === CircuitState::OPEN) {
            $this->logger?->warning(
                'Circuit Breaker открыт, используем fallback',
                [
                    'service' => $this->serviceName,
                    'state' => $state->value,
                ]
            );

            if ($fallback !== null) {
                $this->incrementFallbackCount();

                return $fallback();
            }

            throw new CircuitBreakerOpenException($this->serviceName);
        }

        // Если полуоткрыт - пробуем выполнить один запрос
        if ($state === CircuitState::HALF_OPEN) {
            return $this->executeHalfOpen($operation, $fallback);
        }

        // Нормальное выполнение
        return $this->executeClosed($operation, $fallback);
    }

    public function getState(): CircuitState
    {
        $stateKey = $this->getStateKey();
        $storedState = $this->redis->get($stateKey);

        if ($storedState === false) {
            return CircuitState::CLOSED;
        }

        $state = CircuitState::tryFrom($storedState);

        // Проверяем, не истекло ли время для OPEN состояния
        if ($state === CircuitState::OPEN) {
            $lastFailureKey = $this->getLastFailureKey();
            $lastFailure = $this->redis->get($lastFailureKey);

            if ($lastFailure !== false) {
                $elapsedTime = time() - (int) $lastFailure;
                if ($elapsedTime >= $this->openTimeout) {
                    $this->transitionToHalfOpen();

                    return CircuitState::HALF_OPEN;
                }
            }
        }

        return $state ?? CircuitState::CLOSED;
    }

    public function recordFailure(): void
    {
        $statsKey = $this->getStatsKey();
        $failureCountKey = $this->getFailureCountKey();
        $lastFailureKey = $this->getLastFailureKey();

        $this->redis->hIncrBy($statsKey, 'failed', 1);
        $this->redis->hIncrBy($statsKey, 'total', 1);
        $this->redis->hSet($statsKey, 'last_failure', (string) time());
        $this->redis->hSet($statsKey, 'consecutive_successes', '0');
        $this->redis->incr($failureCountKey);
        $this->redis->set($lastFailureKey, (string) time());
        $this->redis->expire($failureCountKey, $this->openTimeout * 2);
        $this->redis->expire($lastFailureKey, $this->openTimeout * 2);
        $this->redis->expire($statsKey, 86400);

        $this->logger?->error(
            'Circuit Breaker зафиксировал ошибку',
            [
                'service' => $this->serviceName,
                'consecutive_failures' => $this->getConsecutiveFailures(),
            ]
        );

        if ($this->getConsecutiveFailures() >= $this->failureThreshold) {
            $this->transitionToOpen();
        }
    }

    public function getStatistics(): CircuitStatistics
    {
        $statsKey = $this->getStatsKey();
        $statsData = $this->redis->hGetAll($statsKey);

        if ($statsData === false || $statsData === []) {
            return new CircuitStatistics();
        }

        $lastFailureTime = isset($statsData['last_failure'])
            ? new \DateTimeImmutable('@'.$statsData['last_failure'])
            : null;
        $lastSuccessTime = isset($statsData['last_success'])
            ? new \DateTimeImmutable('@'.$statsData['last_success'])
            : null;

        return new CircuitStatistics(
            totalCalls: (int) ($statsData['total'] ?? 0),
            successfulCalls: (int) ($statsData['success'] ?? 0),
            failedCalls: (int) ($statsData['failed'] ?? 0),
            fallbackCalls: (int) ($statsData['fallback'] ?? 0),
            consecutiveFailures: (int) ($statsData['consecutive_failures'] ?? 0),
            consecutiveSuccesses: (int) ($statsData['consecutive_successes'] ?? 0),
            lastFailureTime: $lastFailureTime,
            lastSuccessTime: $lastSuccessTime,
            lastStateChangeTime: $lastFailureTime ?? $lastSuccessTime
        );
    }

    public function reset(): void
    {
        $this->transitionToClosed();
        $this->logger?->info(
            'Circuit Breaker сброшен вручную',
            [
                'service' => $this->serviceName,
            ]
        );
    }

    private function getStateKey(): string
    {
        return self::KEY_PREFIX.$this->serviceName.':state';
    }

    private function getLastFailureKey(): string
    {
        return self::KEY_PREFIX.$this->serviceName.':last_failure';
    }

    private function transitionToHalfOpen(): void
    {
        $stateKey = $this->getStateKey();
        $this->redis->setex($stateKey, 10, CircuitState::HALF_OPEN->value);

        $this->logger?->info(
            'Circuit Breaker перешел в состояние HALF_OPEN',
            [
                'service' => $this->serviceName,
            ]
        );
    }

    private function incrementFallbackCount(): void
    {
        $statsKey = $this->getStatsKey();
        $this->redis->hIncrBy($statsKey, 'fallback', 1);
    }

    private function getStatsKey(): string
    {
        return self::KEY_PREFIX.$this->serviceName.':stats';
    }

    private function executeHalfOpen(callable $operation, ?callable $fallback): mixed
    {
        try {
            $result = $operation();

            // Успех - проверяем, достигли ли порога для закрытия
            $successes = $this->incrementConsecutiveSuccesses();
            if ($successes >= $this->successThreshold) {
                $this->transitionToClosed();
            }

            return $result;
        } catch (\Throwable $e) {
            // Любая ошибка сразу возвращает в открытое состояние
            $this->transitionToOpen();

            if ($fallback !== null) {
                $this->incrementFallbackCount();

                return $fallback();
            }

            throw $e;
        }
    }

    private function incrementConsecutiveSuccesses(): int
    {
        $statsKey = $this->getStatsKey();
        $this->redis->hIncrBy($statsKey, 'consecutive_failures', -1);

        return (int) $this->redis->hIncrBy($statsKey, 'consecutive_successes', 1);
    }

    private function transitionToClosed(): void
    {
        $stateKey = $this->getStateKey();
        $failureCountKey = $this->getFailureCountKey();

        $this->redis->set($stateKey, CircuitState::CLOSED->value);
        $this->redis->del($failureCountKey);

        $this->logger?->info(
            'Circuit Breaker перешел в состояние CLOSED',
            [
                'service' => $this->serviceName,
            ]
        );
    }

    private function getFailureCountKey(): string
    {
        return self::KEY_PREFIX.$this->serviceName.':failures';
    }

    private function transitionToOpen(): void
    {
        $stateKey = $this->getStateKey();
        $this->redis->set($stateKey, CircuitState::OPEN->value);
        $this->redis->setex($stateKey, $this->openTimeout, CircuitState::OPEN->value);

        $this->logger?->warning(
            'Circuit Breaker перешел в состояние OPEN',
            [
                'service' => $this->serviceName,
                'timeout' => $this->openTimeout,
            ]
        );
    }

    private function executeClosed(callable $operation, ?callable $fallback): mixed
    {
        try {
            $result = $operation();
            $this->recordSuccess();

            return $result;
        } catch (\Throwable $e) {
            $this->recordFailure();

            // Проверяем, не достигли ли мы порога срабатывания
            $failures = $this->getConsecutiveFailures();
            if ($failures >= $this->failureThreshold) {
                $this->transitionToOpen();
            }

            // Если есть fallback - используем его
            if ($fallback !== null) {
                $this->incrementFallbackCount();

                return $fallback();
            }

            throw $e;
        }
    }

    private function recordSuccess(): void
    {
        $statsKey = $this->getStatsKey();
        $this->redis->hIncrBy($statsKey, 'success', 1);
        $this->redis->hIncrBy($statsKey, 'total', 1);
        $this->redis->hSet($statsKey, 'last_success', (string) time());
        $this->redis->hIncrBy($statsKey, 'consecutive_failures', -1);
        $this->redis->hIncrBy($statsKey, 'consecutive_successes', 1);
        $this->redis->expire($statsKey, 86400); // 24 часа
    }

    private function getConsecutiveFailures(): int
    {
        $failureCountKey = $this->getFailureCountKey();
        $count = $this->redis->get($failureCountKey);

        return $count !== false ? (int) $count : 0;
    }
}
