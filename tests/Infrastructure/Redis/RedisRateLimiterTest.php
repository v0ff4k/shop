<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Redis;

use App\Infrastructure\Redis\RedisRateLimiter;
use PHPUnit\Framework\TestCase;

final class RedisRateLimiterTest extends TestCase
{
    public function testHitAllowsRequestWithinLimit(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('eval')
            ->with(
                $this->stringContains("redis.call('incr', KEYS[1])"),
                ['shop:rate_limit:search', 60],
                1
            )
            ->willReturn([1, 59]);

        $result = (new RedisRateLimiter($redis))->hit('search', 2, 60);

        self::assertTrue($result->allowed);
        self::assertSame(1, $result->used);
        self::assertSame(1, $result->remaining);
        self::assertSame(59, $result->retryAfter);
    }

    public function testHitDeniesRequestAboveLimit(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('eval')->willReturn([3, 42]);

        $result = (new RedisRateLimiter($redis))->hit('search', 2, 60);

        self::assertFalse($result->allowed);
        self::assertSame(0, $result->remaining);
        self::assertSame(42, $result->retryAfter);
    }
}
