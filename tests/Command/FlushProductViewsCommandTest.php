<?php

declare(strict_types=1);

namespace Tests\Command;

use App\Application\Service\ProductViewTracker;
use App\Command\FlushProductViewsCommand;
use App\Infrastructure\Redis\RedisQueue;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class FlushProductViewsCommandTest extends TestCase
{
    public function testFlushesAggregatedViews(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('rPop')
            ->with('shop:queue:'.ProductViewTracker::QUEUE_NAME)
            ->willReturnOnConsecutiveCalls('1', '1', '2', false);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('beginTransaction');
        $connection->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(static function (string $sql, array $params): int {
                self::assertStringContainsString('viewed = viewed + :count', $sql);
                self::assertContains($params['id'], [1, 2]);

                return 1;
            });
        $connection->expects($this->once())->method('commit');

        $command = new FlushProductViewsCommand(
            new RedisQueue($redis),
            $connection,
            $this->createMock(LoggerInterface::class)
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('Flushed 3 views for 2 products', $tester->getDisplay());
    }

    public function testDoesNothingForEmptyQueue(): void
    {
        $redis = $this->createMock(\Redis::class);
        $redis->method('rPop')->willReturn(false);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('beginTransaction');

        $command = new FlushProductViewsCommand(
            new RedisQueue($redis),
            $connection,
            $this->createMock(LoggerInterface::class)
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        self::assertSame(0, $tester->getStatusCode());
        self::assertStringContainsString('No buffered product views', $tester->getDisplay());
    }
}
