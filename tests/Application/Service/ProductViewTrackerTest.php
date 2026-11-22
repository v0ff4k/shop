<?php

declare(strict_types=1);

namespace Tests\Application\Service;

use App\Application\Service\ProductViewTracker;
use App\Infrastructure\Redis\RedisCounter;
use App\Infrastructure\Redis\RedisQueue;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ProductViewTrackerTest extends TestCase
{
    public function testRecordPushesQueueAndIncrementsCounter(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->expects($this->once())
            ->method('lPush')
            ->with('shop:queue:'.ProductViewTracker::QUEUE_NAME, '42')
            ->willReturn(1);
        $redis->expects($this->once())
            ->method('incr')
            ->with('shop:counter:product:views:42')
            ->willReturn(1);
        $redis->expects($this->once())
            ->method('expire')
            ->with('shop:counter:product:views:42', 2592000);

        $tracker = new ProductViewTracker(
            new RedisCounter($redis),
            new RedisQueue($redis),
            $this->createMock(LoggerInterface::class)
        );

        self::assertTrue($tracker->record(42));
    }

    public function testRecordReturnsFalseWhenRedisFails(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('lPush')->willThrowException(new \RuntimeException('redis down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');

        $tracker = new ProductViewTracker(
            new RedisCounter($redis),
            new RedisQueue($redis),
            $logger
        );

        self::assertFalse($tracker->record(42));
    }
}
