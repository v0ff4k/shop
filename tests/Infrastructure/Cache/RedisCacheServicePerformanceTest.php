<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Cache;

use App\Infrastructure\Cache\RedisCacheService;
use PHPUnit\Framework\TestCase;

/**
 * Тесты для измерения производительности RedisCacheService.
 */
final class RedisCacheServicePerformanceTest extends TestCase
{
    private ?\Redis $redis;
    private ?RedisCacheService $cache;

    protected function setUp(): void
    {
        // Пропускаем тест если Redis недоступен
        if (!\extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension not available');
        }

        try {
            $this->redis = new \Redis();
            if (!$this->redis->connect('redis', 6379)) {
                $this->markTestSkipped('Redis server not available');
            }

            $this->cache = new RedisCacheService($this->redis, 3600);
            $this->cache->clear(); // Очистка перед тестом
        } catch (\Exception $e) {
            $this->markTestSkipped('Redis connection failed: '.$e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        if ($this->cache) {
            $this->cache->clear();
        }
        if ($this->redis) {
            $this->redis->close();
        }
    }

    public function testSetAndGetPerformance(): void
    {
        if (!$this->cache) {
            $this->markTestSkipped('Cache service not available');
        }

        $iterations = 1000;
        $key = 'performance_test';
        $value = ['data' => str_repeat('x', 1000)]; // 1KB данных

        $startTime = microtime(true);

        // Тестирование записи
        for ($i = 0; $i < $iterations; ++$i) {
            $this->cache->set($key.$i, $value);
        }

        $setTime = microtime(true) - $startTime;

        // Тестирование чтения
        $startTime = microtime(true);
        for ($i = 0; $i < $iterations; ++$i) {
            $this->cache->get($key.$i);
        }

        $getTime = microtime(true) - $startTime;

        // Вывод результатов производительности
        $this->assertLessThan(1.0, $setTime, "Set operations too slow: {$setTime}s");
        $this->assertLessThan(1.0, $getTime, "Get operations too slow: {$getTime}s");

        $this->assertTrue(true, "Performance test completed. Set: {$setTime}s, Get: {$getTime}s");
    }

    public function testTagOperationsPerformance(): void
    {
        if (!$this->cache) {
            $this->markTestSkipped('Cache service not available');
        }

        $iterations = 100;
        $key = 'tag_test';
        $tag = 'performance_tag';

        $startTime = microtime(true);

        // Тестирование записи с тегами
        for ($i = 0; $i < $iterations; ++$i) {
            $this->cache->setWithTag($key.$i, ['data' => "value_$i"], $tag);
        }

        $setTime = microtime(true) - $startTime;

        // Тестирование инвалидации по тегу
        $startTime = microtime(true);
        $result = $this->cache->invalidateByTag($tag);
        $invalidateTime = microtime(true) - $startTime;

        $this->assertTrue($result);
        $this->assertLessThan(0.5, $invalidateTime, "Invalidate operation too slow: {$invalidateTime}s");

        $this->assertTrue(true, "Tag performance test completed. Set: {$setTime}s, Invalidate: {$invalidateTime}s");
    }

    public function testBulkOperationsPerformance(): void
    {
        if (!$this->cache) {
            $this->markTestSkipped('Cache service not available');
        }

        $iterations = 100;
        $keys = array_map(static fn ($i) => 'bulk_test_'.$i, range(0, $iterations - 1));

        $startTime = microtime(true);

        // Тестирование записи нескольких ключей
        foreach ($keys as $key) {
            $this->cache->set($key, ['data' => $key]);
        }

        $setTime = microtime(true) - $startTime;

        // Тестирование удаления нескольких ключей
        $startTime = microtime(true);
        $result = $this->cache->deleteMultiple($keys);
        $deleteTime = microtime(true) - $startTime;

        $this->assertTrue($result);
        $this->assertLessThan(0.5, $deleteTime, "Delete multiple operation too slow: {$deleteTime}s");

        $this->assertTrue(true, "Bulk operations performance test completed. Set: {$setTime}s, Delete: {$deleteTime}s");
    }

    public function testMemoryUsage(): void
    {
        if (!$this->cache) {
            $this->markTestSkipped('Cache service not available');
        }

        // Добавляем тестовые данные
        for ($i = 0; $i < 100; ++$i) {
            $this->cache->set('memory_test_'.$i, ['data' => str_repeat('x', 100)]);
        }

        $stats = $this->cache->getStats();

        $this->assertGreaterThan(0, $stats['total_keys']);
        $this->assertGreaterThan(0, $stats['memory_usage_bytes']);

        // Очистка после теста
        $this->cache->clear();
    }
}
