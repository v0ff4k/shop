<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Redis;

use App\Infrastructure\Redis\RedisCounter;
use PHPUnit\Framework\TestCase;

final class RedisCounterTest extends TestCase
{
    public function testIncrementSetsTtlOnlyOnFirstHit(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('incr')
            ->with('shop:counter:product:views:1')
            ->willReturn(1);
        $redis->expects($this->once())
            ->method('expire')
            ->with('shop:counter:product:views:1', 60);

        $counter = new RedisCounter($redis);

        self::assertSame(1, $counter->increment('product:views:1', 60));
    }

    public function testGetReturnsZeroForMissingKey(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('get')->with('shop:counter:x')->willReturn(false);

        self::assertSame(0, (new RedisCounter($redis))->get('x'));
    }

    public function testResetDeletesKey(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('del')
            ->with('shop:counter:x');

        (new RedisCounter($redis))->reset('x');
    }
}
