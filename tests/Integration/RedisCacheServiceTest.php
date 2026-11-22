<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Cache\RedisCacheService;
use Doctrine\ORM\EntityManagerInterface;
use Redis;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RedisCacheServiceTest extends KernelTestCase
{
    private RedisCacheService $cacheService;
    private \Redis $redis;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->cacheService = $container->get(RedisCacheService::class);
        $this->redis = $container->get(\Redis::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);

        // Очищаем Redis перед каждым тестом
        $this->redis->flushDB();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->redis->flushDB();
        if ($this->entityManager->isOpen()) {
            $this->entityManager->close();
        }
    }

    public function testSaveAndRetrieveString(): void
    {
        $key = 'test:string:key';
        $value = 'test value';
        $ttl = 3600;

        $this->assertTrue($this->cacheService->set($key, $value, $ttl));

        $this->assertEquals($value, $this->cacheService->get($key));
        $this->assertTrue($this->cacheService->has($key));
    }

    public function testSaveAndRetrieveArray(): void
    {
        $key = 'test:array:key';
        $value = ['foo' => 'bar', 'numbers' => [1, 2, 3]];
        $ttl = 1800;

        $this->assertTrue($this->cacheService->set($key, $value, $ttl));

        $this->assertEquals($value, $this->cacheService->get($key));
    }

    public function testCacheExpiration(): void
    {
        $key = 'test:expiration:key';
        $value = 'expiring value';
        $ttl = 1; // 1 second

        $this->assertTrue($this->cacheService->set($key, $value, $ttl));

        // Сразу после сохранения значение должно быть доступно
        $this->assertEquals($value, $this->cacheService->get($key));

        // Ждем истечения TTL
        sleep(2);

        // После истечения TTL значение должно быть null
        $this->assertNull($this->cacheService->get($key));
        $this->assertFalse($this->cacheService->has($key));
    }

    public function testDeleteKey(): void
    {
        $key = 'test:delete:key';
        $value = 'to be deleted';

        $this->assertTrue($this->cacheService->set($key, $value));
        $this->assertEquals($value, $this->cacheService->get($key));

        $this->assertTrue($this->cacheService->delete($key));
        $this->assertNull($this->cacheService->get($key));
    }

    public function testSetOverwritesExistingValue(): void
    {
        $key = 'test:overwrite:key';

        $this->cacheService->set($key, 10);
        $this->assertSame(10, $this->cacheService->get($key));

        $this->cacheService->set($key, 11);
        $this->assertSame(11, $this->cacheService->get($key));
    }

    public function testInvalidateByTagRemovesTaggedKeys(): void
    {
        $tag = 'test-tag';

        $this->assertTrue($this->cacheService->setWithTag('test:tag:key1', 'value1', $tag));
        $this->assertTrue($this->cacheService->setWithTag('test:tag:key2', 'value2', $tag));
        $this->assertTrue($this->cacheService->set('test:untagged:key', 'value3'));

        $this->assertTrue($this->cacheService->invalidateByTag($tag));

        $this->assertNull($this->cacheService->get('test:tag:key1'));
        $this->assertNull($this->cacheService->get('test:tag:key2'));
        $this->assertSame('value3', $this->cacheService->get('test:untagged:key'));
    }

    public function testDeleteByPattern(): void
    {
        $this->cacheService->set('test:pattern:key1', 'value1');
        $this->cacheService->set('test:pattern:key2', 'value2');
        $this->cacheService->set('other:key', 'value3');

        $this->assertTrue($this->cacheService->deleteByPattern('test:pattern:*'));

        $this->assertFalse($this->cacheService->has('test:pattern:key1'));
        $this->assertFalse($this->cacheService->has('test:pattern:key2'));
        $this->assertTrue($this->cacheService->has('other:key'));
    }
}
