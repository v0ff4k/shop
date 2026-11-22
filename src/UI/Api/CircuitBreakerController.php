<?php

declare(strict_types=1);

namespace App\UI\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * API контроллер для управления Circuit Breaker.
 */
#[Route('/api/circuit-breaker')]
final class CircuitBreakerController
{
    public function __construct(
        private readonly CircuitBreakerManager $manager
    ) {
    }

    /**
     * Получить статистику всех Circuit Breaker.
     */
    #[Route('/stats', name: 'api_circuit_breaker_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $statistics = $this->manager->getAllStatistics();

        return new JsonResponse(
            [
                'success' => true,
                'data' => $statistics,
            ]
        );
    }

    /**
     * Получить состояние конкретного Circuit Breaker.
     */
    #[Route('/{service}/state', name: 'api_circuit_breaker_state', methods: ['GET'])]
    public function state(string $service): JsonResponse
    {
        try {
            $state = $this->manager->getState($service);

            return new JsonResponse(
                [
                    'success' => true,
                    'data' => [
                        'service' => $service,
                        'state' => $state,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            return new JsonResponse(
                [
                    'success' => false,
                    'error' => $e->getMessage(),
                ], 404
            );
        }
    }

    /**
     * Сбросить конкретный Circuit Breaker.
     */
    #[Route('/{service}/reset', name: 'api_circuit_breaker_reset', methods: ['POST'])]
    public function reset(string $service): JsonResponse
    {
        try {
            $this->manager->reset($service);

            return new JsonResponse(
                [
                    'success' => true,
                    'message' => \sprintf('Circuit Breaker "%s" сброшен', $service),
                ]
            );
        } catch (\Throwable $e) {
            return new JsonResponse(
                [
                    'success' => false,
                    'error' => $e->getMessage(),
                ], 500
            );
        }
    }

    /**
     * Сбросить все Circuit Breaker.
     */
    #[Route('/reset-all', name: 'api_circuit_breaker_reset_all', methods: ['POST'])]
    public function resetAll(): JsonResponse
    {
        try {
            $this->manager->resetAll();

            return new JsonResponse(
                [
                    'success' => true,
                    'message' => 'Все Circuit Breaker сброшены',
                ]
            );
        } catch (\Throwable $e) {
            return new JsonResponse(
                [
                    'success' => false,
                    'error' => $e->getMessage(),
                ], 500
            );
        }
    }
}
