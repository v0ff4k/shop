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
final class CircuitBreakerListController implements ControllerInterface
{
    private \Redis $redis;

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * GET /api/circuit-breaker/stats - Список всех circuit breaker.
     */
    public function __invoke(): JsonResponse
    {
        $pattern = 'circuit_breaker:*:state';
        $keys = $this->redis->keys($pattern);

        $breakers = [];
        foreach ($keys as $key) {
            if (preg_match('/circuit_breaker:(.+):state/', $key, $matches)) {
                $serviceName = $matches[1];
                $circuitBreaker = new RedisCircuitBreaker($this->redis, $serviceName);
                $state = $circuitBreaker->getState();
                $statistics = $circuitBreaker->getStatistics();

                $breakers[] = [
                    'service' => $serviceName,
                    'state' => $state->value,
                    'failure_rate' => $statistics->getFailureRate(),
                    'success_count' => $statistics->successCount,
                    'failure_count' => $statistics->failureCount,
                    'rejected_count' => $statistics->rejectedCount,
                    'total_calls' => $statistics->getTotalCalls(),
                    'last_failure' => $statistics->lastFailureTime?->format(\DateTimeInterface::ATOM),
                    'next_retry' => $statistics->nextRetryTime?->format(\DateTimeInterface::ATOM),
                ];
            }
        }

        return new JsonResponse([
            'success' => true,
            'data' => $breakers,
        ]);
    }
}
