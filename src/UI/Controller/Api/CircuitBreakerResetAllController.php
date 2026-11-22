<?php

declare(strict_types=1);

namespace App\UI\Controller\Api;

use App\Infrastructure\Resilience\RedisCircuitBreaker;
use App\UI\Controller\ControllerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * API контроллер для сброса всех circuit breaker.
 *
 * ТОЛЬКО принимает DTO и возвращает Response
 * Никакой бизнес-логики
 */
final class CircuitBreakerResetAllController implements ControllerInterface
{
    private \Redis $redis;

    public function __construct(\Redis $redis)
    {
        $this->redis = $redis;
    }

    /**
     * POST /api/circuit-breaker/reset-all - Сброс всех circuit breaker.
     */
    public function __invoke(): JsonResponse
    {
        $pattern = 'circuit_breaker:*:state';
        $keys = $this->redis->keys($pattern);

        $resetCount = 0;
        foreach ($keys as $key) {
            if (preg_match('/circuit_breaker:(.+):state/', $key, $matches)) {
                $serviceName = $matches[1];
                $circuitBreaker = new RedisCircuitBreaker(
                    $this->redis,
                    $serviceName
                );
                $circuitBreaker->reset();
                ++$resetCount;
            }
        }

        return new JsonResponse([
            'success' => true,
            'reset_count' => $resetCount,
        ]);
    }
}
