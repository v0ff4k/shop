<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Cache;

use App\Infrastructure\Cache\RedisCacheService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Тесты для RedisCacheService.
 *
 * @covers \App\Infrastructure\Cache\RedisCacheService
 */
final class RedisCacheServiceTest extends TestCase
{
    private \Redis|MockObject $redis;
    private RedisCacheService $cache;

    protected function setUp(): void
    {
        $this->redis = $this->createMock(\Redis::class);
        $this->cache = new RedisCacheService($this->redis, 3600);
    }

    public function testSetAndGet(): void
    {
        // Arrange
        $key = 'test_key';
        $value = ['name' => 'Test', 'value' => 123];
        $serialized = serialize($value);

        $this->redis
            ->expects($this->once())
            ->method('setex')
            ->with('shop_cache:'.$key, 3600, $serialized)
            ->willReturn(true);

        $this->redis
            ->expects($this->once())
            ->method('get')
            ->with('shop_cache:'.$key)
            ->willReturn($serialized);

        // Act
        $this->cache->set($key, $value);
        $result = $this->cache->get($key);

        // Assert
        $this->assertEquals($value, $result);
    }

    public function testGetNonExistentKey(): void
    {
        // Arrange
        $this->redis
            ->expects($this->once())
            ->method('get')
            ->with('shop_cache:non_existent')
            ->willReturn(false);

        // Act
        $result = $this->cache->get('non_existent');

        // Assert
        $this->assertNull($result);
    }

    public function testGetWithDefaultValue(): void
    {
        // Arrange
        $default = 'default_value';

        $this->redis
            ->expects($this->once())
            ->method('get')
            ->with('shop_cache:non_existent')
            ->willReturn(false);

        // Act
        $result = $this->cache->get('non_existent', $default);

        // Assert
        $this->assertEquals($default, $result);
    }

    public function testHas(): void
    {
        // Arrange
        $this->redis
            ->expects($this->once())
            ->method('exists')
            ->with('shop_cache:test_key')
            ->willReturn(1);

        // Act
        $result = $this->cache->has('test_key');

        // Assert
        $this->assertTrue($result);
    }

    public function testDelete(): void
    {
        // Arrange
        $this->redis
            ->expects($this->once())
            ->method('del')
            ->with('shop_cache:test_key')
            ->willReturn(1);

        // Act
        $result = $this->cache->delete('test_key');

        // Assert
        $this->assertTrue($result);
    }

    public function testSetWithTag(): void
    {
        // Arrange
        $key = 'test_key';
        $value = 'test_value';
        $tag = 'test_tag';
        $serialized = serialize($value);

        $this->redis
            ->expects($this->once())
            ->method('setex')
            ->with('shop_cache:'.$key, 3600, $serialized)
            ->willReturn(true);

        $this->redis
            ->expects($this->once())
            ->method('sAdd')
            ->with('shop_cache_tag:'.$tag, 'shop_cache:'.$key);

        $this->redis
            ->expects($this->once())
            ->method('expire')
            ->with('shop_cache_tag:'.$tag, 3600);

        // Act
        $result = $this->cache->setWithTag($key, $value, $tag);

        // Assert
        $this->assertTrue($result);
    }

    public function testInvalidateByTag(): void
    {
        // Arrange
        $tag = 'test_tag';
        $keys = ['shop_cache:key1', 'shop_cache:key2'];

        $this->redis
            ->expects($this->once())
            ->method('sMembers')
            ->with('shop_cache_tag:'.$tag)
            ->willReturn($keys);

        $this->redis
            ->expects($this->exactly(2))
            ->method('del')
            ->willReturnCallback(function (array|string $key) use ($keys, $tag): int {
                if (\is_array($key)) {
                    $this->assertSame($keys, $key);
                } else {
                    $this->assertSame('shop_cache_tag:'.$tag, $key);
                }

                return 1;
            });

        // Act
        $result = $this->cache->invalidateByTag($tag);

        // Assert
        $this->assertTrue($result);
    }

    public function testInvalidateByTagWithNoKeys(): void
    {
        // Arrange
        $tag = 'empty_tag';

        $this->redis
            ->expects($this->once())
            ->method('sMembers')
            ->with('shop_cache_tag:'.$tag)
            ->willReturn([]);

        // Act
        $result = $this->cache->invalidateByTag($tag);

        // Assert
        $this->assertTrue($result);
    }

    public function testDeleteMultiple(): void
    {
        // Arrange
        $keys = ['key1', 'key2', 'key3'];
        $fullKeys = ['shop_cache:key1', 'shop_cache:key2', 'shop_cache:key3'];

        $this->redis
            ->expects($this->once())
            ->method('del')
            ->with($fullKeys)
            ->willReturn(3);

        // Act
        $result = $this->cache->deleteMultiple($keys);

        // Assert
        $this->assertTrue($result);
    }

    public function testDeleteMultipleWithEmptyArray(): void
    {
        // Arrange
        $this->redis
            ->expects($this->never())
            ->method('del');

        // Act
        $result = $this->cache->deleteMultiple([]);

        // Assert
        $this->assertTrue($result);
    }

    public function testClear(): void
    {
        // Arrange
        $cacheKeys = ['shop_cache:key1', 'shop_cache:key2'];
        $tagKeys = ['shop_cache_tag:tag1', 'shop_cache_tag:tag2'];

        $this->redis
            ->expects($this->exactly(2))
            ->method('scan')
            ->willReturnCallback(function (mixed &$iterator, string $pattern) use ($cacheKeys, $tagKeys): array {
                $iterator = 0;

                return match ($pattern) {
                    'shop_cache:*' => $cacheKeys,
                    'shop_cache_tag:*' => $tagKeys,
                    default => $this->fail('Unexpected scan pattern: '.$pattern),
                };
            });

        $this->redis
            ->expects($this->exactly(2))
            ->method('del')
            ->willReturnCallback(static function (array $keys): int {
                return \count($keys);
            });

        // Act
        $result = $this->cache->clear();

        // Assert
        $this->assertTrue($result);
    }

    public function testSetWithCustomTtl(): void
    {
        // Arrange
        $key = 'test_key';
        $value = 'test_value';
        $ttl = 7200;
        $serialized = serialize($value);

        $this->redis
            ->expects($this->once())
            ->method('setex')
            ->with('shop_cache:'.$key, $ttl, $serialized)
            ->willReturn(true);

        // Act
        $result = $this->cache->set($key, $value, $ttl);

        // Assert
        $this->assertTrue($result);
    }

    public function testDeleteByPattern(): void
    {
        // Arrange
        $pattern = 'test_*';
        $matchingKeys = ['shop_cache:test_key1', 'shop_cache:test_key2'];

        $this->redis
            ->expects($this->once())
            ->method('scan')
            ->willReturnCallback(function (mixed &$iterator, string $pattern, int $count) use ($matchingKeys): array {
                $iterator = 0;
                $this->assertSame('shop_cache:test_*', $pattern);
                $this->assertSame(200, $count);

                return $matchingKeys;
            });

        $this->redis
            ->expects($this->once())
            ->method('del')
            ->with($matchingKeys)
            ->willReturn(2);

        // Act
        $result = $this->cache->deleteByPattern($pattern);

        // Assert
        $this->assertTrue($result);
    }

    public function testGetStats(): void
    {
        // Arrange
        $cacheKeys = ['shop_cache:key1', 'shop_cache:key2'];
        $tagKeys = ['shop_cache_tag:tag1'];
        $bytesPerKey = 2 * 1024 * 1024;

        $this->redis
            ->expects($this->exactly(2))
            ->method('scan')
            ->willReturnCallback(function (mixed &$iterator, string $pattern) use ($cacheKeys, $tagKeys): array {
                $iterator = 0;

                return match ($pattern) {
                    'shop_cache:*' => $cacheKeys,
                    'shop_cache_tag:*' => $tagKeys,
                    default => $this->fail('Unexpected scan pattern: '.$pattern),
                };
            });

        $this->redis
            ->expects($this->exactly(\count($cacheKeys)))
            ->method('rawCommand')
            ->with('MEMORY', 'USAGE', $this->isType('string'))
            ->willReturn($bytesPerKey);

        // Act
        $stats = $this->cache->getStats();

        // Assert
        $this->assertEquals(2, $stats['total_keys']);
        $this->assertEquals(1, $stats['total_tags']);
        $this->assertEquals(2 * $bytesPerKey, $stats['memory_usage_bytes']);
        $this->assertEquals(4.0, $stats['memory_usage_mb']);
    }
}
