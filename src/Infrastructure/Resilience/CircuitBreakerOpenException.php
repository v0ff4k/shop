<?php

declare(strict_types=1);

namespace App\Infrastructure\Resilience;

/**
 * Исключение, выбрасываемое при разомкнутом Circuit Breaker.
 */
final class CircuitBreakerOpenException extends \RuntimeException
{
    public function __construct(
        string $service,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            \sprintf('Circuit Breaker открыт для сервиса: %s', $service),
            503,
            $previous
        );
    }
}
