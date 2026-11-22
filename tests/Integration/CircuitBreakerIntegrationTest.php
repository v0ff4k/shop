<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Resilience\CircuitBreakerFactory;
use App\Infrastructure\Resilience\CircuitBreakerOpenException;
use App\Infrastructure\Resilience\CircuitState;
use Redis;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CircuitBreakerIntegrationTest extends KernelTestCase
{
    private \Redis $redis;
    private CircuitBreakerFactory $factory;
    private string $testServiceName = 'test_integration_service';

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->redis = $container->get(\Redis::class);
        // Фабрика приватная и инлайнится при компиляции контейнера,
        // поэтому собираем её напрямую с реальным Redis.
        $this->factory = new CircuitBreakerFactory($this->redis);

        // Очищаем ключи circuit breaker для нашего тестового сервиса
        $this->cleanupCircuitBreakerKeys();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->cleanupCircuitBreakerKeys();
    }

    public function testCircuitBreakerStartsClosed(): void
    {
        $circuitBreaker = $this->factory->create($this->testServiceName);

        $state = $circuitBreaker->getState();
        $this->assertSame(CircuitState::CLOSED, $state);
    }

    public function testCircuitBreakerOpensAfterFailures(): void
    {
        $circuitBreaker = $this->factory->create($this->testServiceName);

        // Симулируем неудачные вызовы
        for ($i = 0; $i < 5; ++$i) {
            $circuitBreaker->recordFailure();
        }

        $state = $circuitBreaker->getState();
        $this->assertSame(CircuitState::OPEN, $state);

        // Проверяем, что попытка выполнить действие бросит исключение
        $this->expectException(CircuitBreakerOpenException::class);

        $circuitBreaker->execute(static fn () => throw new \RuntimeException('Simulated failure'));
    }

    public function testCircuitBreakerHalfOpenAfterTimeout(): void
    {
        $openTimeout = 30;
        $circuitBreaker = $this->factory->create(
            $this->testServiceName.'_halfopen',
            successThreshold: 1,
            openTimeout: $openTimeout
        );

        // Приводим к открытому состоянию
        for ($i = 0; $i < 5; ++$i) {
            $circuitBreaker->recordFailure();
        }

        $this->assertSame(CircuitState::OPEN, $circuitBreaker->getState());

        // Имитируем истечение таймаута открытого состояния
        $this->redis->set(
            'circuit_breaker:'.$this->testServiceName.'_halfopen:last_failure',
            (string) (time() - $openTimeout - 1)
        );

        $this->assertSame(CircuitState::HALF_OPEN, $circuitBreaker->getState());

        // Успешный вызов должен закрыть circuit breaker
        $this->assertSame('ok', $circuitBreaker->execute(static fn () => 'ok'));

        $this->assertSame(CircuitState::CLOSED, $circuitBreaker->getState());
    }

    public function testSuccessfulExecutionWhenClosed(): void
    {
        $circuitBreaker = $this->factory->create($this->testServiceName.'_success');

        $result = $circuitBreaker->execute(static fn () => 'success');

        $this->assertEquals('success', $result);

        // Проверяем, что состояние все еще закрыто
        $this->assertSame(CircuitState::CLOSED, $circuitBreaker->getState());

        $stats = $circuitBreaker->getStatistics();
        $this->assertSame(1, $stats->successfulCalls);
        $this->assertSame(0, $stats->failedCalls);
    }

    public function testFailedExecutionRecordsFailure(): void
    {
        $circuitBreaker = $this->factory->create($this->testServiceName.'_failure');

        try {
            $circuitBreaker->execute(static fn () => throw new \RuntimeException('Test failure'));
            $this->fail('Ожидалось исключение от неудачного выполнения');
        } catch (\RuntimeException $e) {
            $this->assertSame('Test failure', $e->getMessage());
        }

        // Проверяем, что неудача была записана
        $stats = $circuitBreaker->getStatistics();
        $this->assertSame(1, $stats->failedCalls);
        $this->assertSame(0, $stats->successfulCalls);
    }

    private function cleanupCircuitBreakerKeys(): void
    {
        $pattern = 'circuit_breaker:'.$this->testServiceName.'*';
        $keys = $this->redis->keys($pattern);
        foreach ($keys as $key) {
            $this->redis->del($key);
        }
    }
}
