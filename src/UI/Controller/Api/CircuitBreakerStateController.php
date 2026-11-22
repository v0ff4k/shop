<?php

declare(strict_types=1);

namespace App\UI\Controller\Api;

use App\Infrastructure\Resilience\RedisCircuitBreaker;
use App\UI\Controller\ControllerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * API контроллер для отображения статуса circuit breaker.
 *
 * ТОЛЬКО принимает DTO и возвращает Response
 * Никакой бизнес-логики
 */
final class CircuitBreakerStateController implements ControllerInterface
{
    private \Redis $redis;

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * GET /api/circuit-breaker/{service}/state - Статус конкретного circuit breaker.
     */
    public function __invoke(string $service): JsonResponse
    {
        $circuitBreaker = new RedisCircuitBreaker($this->redis, $service);
        $state = $circuitBreaker->getState();
        $statistics = $circuitBreaker->getStatistics();

        return new JsonResponse([
            'success' => true,
            'data' => [
                'service' => $service,
                'state' => $state->value,
                'statistics' => [
                    'total_calls' => $statistics->totalCalls,
                    'successful_calls' => $statistics->successfulCalls,
                    'failed_calls' => $statistics->failedCalls,
                    'fallback_calls' => $statistics->fallbackCalls,
                    'failure_rate' => $statistics->getFailureRate(),
                    'success_rate' => $statistics->getSuccessRate(),
                ],
            ],
        ]);
    }
}
