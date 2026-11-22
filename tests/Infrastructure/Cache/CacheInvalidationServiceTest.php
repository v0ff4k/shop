<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Cache;

use App\Infrastructure\Cache\CacheInvalidationService;
use App\Infrastructure\Cache\CacheServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CacheInvalidationServiceTest extends TestCase
{
    private CacheServiceInterface&MockObject $cache;
    private CacheInvalidationService $invalidationService;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheServiceInterface::class);
        $this->invalidationService = new CacheInvalidationService(
            $this->cache,
            new NullLogger()
        );
    }

    public function testInvalidateByTag(): void
    {
        $this->cache
            ->expects($this->once())
            ->method('invalidateByTag')
            ->with('products');

        $this->invalidationService->invalidateByTag('products');
    }

    public function testInvalidateByMultipleTags(): void
    {
        $this->cache
            ->expects($this->exactly(2))
            ->method('invalidateByTag')
            ->willReturnCallback(function (string $tag): bool {
                $this->assertContains($tag, ['products', 'categories']);

                return true;
            });

        $this->invalidationService->invalidateByTag('products');
        $this->invalidationService->invalidateByTag('categories');
    }

    public function testInvalidateByKey(): void
    {
        $this->cache
            ->expects($this->once())
            ->method('delete')
            ->with('product_123');

        $this->invalidationService->invalidateByKey('product_123');
    }

    public function testInvalidateByMultipleKeys(): void
    {
        $keys = ['product_123', 'product_456'];

        $this->cache
            ->expects($this->exactly(2))
            ->method('delete')
            ->willReturnCallback(function (string $key): bool {
                $this->assertContains($key, ['product_123', 'product_456']);

                return true;
            });

        foreach ($keys as $key) {
            $this->invalidationService->invalidateByKey($key);
        }
    }

    public function testInvalidatePattern(): void
    {
        $pattern = 'product_*';

        $this->cache
            ->expects($this->once())
            ->method('deleteByPattern')
            ->with($pattern);

        $this->invalidationService->invalidatePattern($pattern);
    }
}
