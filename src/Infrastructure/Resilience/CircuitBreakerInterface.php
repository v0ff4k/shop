<?php

declare(strict_types=1);

namespace App\Infrastructure\Resilience;

/**
 * Интерфейс Circuit Breaker для защиты от сбоев внешних сервисов.
 */
interface CircuitBreakerInterface
{
    /**
     * Выполнить операцию с защитой Circuit Breaker.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @throws CircuitBreakerOpenException
     *
     * @return T
     */
    public function execute(callable $operation, ?callable $fallback = null): mixed;

    /**
     * Получить текущее состояние Circuit Breaker.
     */
    public function getState(): CircuitState;

    /**
     * Получить статистику работы Circuit Breaker.
     */
    public function getStatistics(): CircuitStatistics;

    /**
     * Сбросить Circuit Breaker в закрытое состояние.
     */
    public function reset(): void;
}
