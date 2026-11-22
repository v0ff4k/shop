<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Redis;

use App\Infrastructure\Redis\RedisQueue;
use PHPUnit\Framework\TestCase;

final class RedisQueueTest extends TestCase
{
    public function testPushTrimsQueueWhenLimitConfigured(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('lPush')
            ->with('shop:queue:views', '42')
            ->willReturn(10);
        $redis->expects($this->once())
            ->method('lTrim')
            ->with('shop:queue:views', 0, 99);

        $queue = new RedisQueue($redis);

        self::assertSame(10, $queue->push('views', '42', 100));
    }

    public function testPopReturnsNullForEmptyQueue(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('rPop')->with('shop:queue:views')->willReturn(false);

        self::assertNull((new RedisQueue($redis))->pop('views'));
    }

    public function testPopReturnsPayload(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('rPop')->with('shop:queue:views')->willReturn('42');

        self::assertSame('42', (new RedisQueue($redis))->pop('views'));
    }

    public function testSizeAndClear(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('lLen')->with('shop:queue:views')->willReturn(3);
        $redis->expects($this->once())->method('del')->with('shop:queue:views');

        $queue = new RedisQueue($redis);

        self::assertSame(3, $queue->size('views'));
        $queue->clear('views');
    }
}
