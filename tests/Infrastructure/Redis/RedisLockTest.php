<?php

declare(strict_types=1);

namespace Tests\Infrastructure\Redis;

use App\Infrastructure\Redis\RedisLock;
use PHPUnit\Framework\TestCase;

final class RedisLockTest extends TestCase
{
    public function testAcquireReturnsTokenWhenLockIsFree(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('set')
            ->with(
                'shop:lock:email:send:abc',
                $this->isType('string'),
                ['nx', 'ex' => 30]
            )
            ->willReturn(true);

        $token = (new RedisLock($redis))->acquire('email:send:abc', 30);

        self::assertNotNull($token);
        self::assertSame(32, \strlen($token));
    }

    public function testAcquireReturnsNullWhenLockIsHeld(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('set')->willReturn(false);

        self::assertNull((new RedisLock($redis))->acquire('email:send:abc', 30));
    }

    public function testReleaseUsesTokenSafeLuaScript(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('eval')
            ->with(
                $this->stringContains("redis.call('get', KEYS[1])"),
                ['shop:lock:email:send:abc', 'token'],
                1
            )
            ->willReturn(1);

        (new RedisLock($redis))->release('email:send:abc', 'token');
    }
}
