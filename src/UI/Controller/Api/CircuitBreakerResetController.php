<?php

declare(strict_types=1);

namespace App\UI\Controller\Api;

use App\Infrastructure\Resilience\RedisCircuitBreaker;
use App\UI\Controller\ControllerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * API контроллер для сброса circuit breaker.
 *
 * ТОЛЬКО принимает DTO и возвращает Response
 * Никакой бизнес-логики
 */
final class CircuitBreakerResetController implements ControllerInterface
{
    private \Redis $redis;

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * POST /api/circuit-breaker/{service}/reset - Сброс circuit breaker.
     */
    public function __invoke(string $service): JsonResponse
    {
        $circuitBreaker = new RedisCircuitBreaker($this->redis, $service);
        $circuitBreaker->reset();

        return new JsonResponse([
            'success' => true,
            'data' => [
                'service' => $service,
            ],
        ]);
    }
}
