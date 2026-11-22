<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\OpenTelemetry\OpenTelemetryTracer;
use App\Infrastructure\Persistence\Middleware\DoctrineTracingMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Result as DriverResult;
use Doctrine\DBAL\Driver\Statement as DriverStatement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class DoctrineTracingMiddlewareTest extends TestCase
{
    private OpenTelemetryTracer&MockObject $tracer;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->tracer = $this->createMock(OpenTelemetryTracer::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testWrapTracesPreparedStatementExecution(): void
    {
        $result = $this->createMock(DriverResult::class);
        $statement = $this->createMock(DriverStatement::class);
        $statement->expects($this->once())
            ->method('execute')
            ->willReturn($result);

        $connection = $this->createMock(DriverConnection::class);
        $connection->expects($this->once())
            ->method('prepare')
            ->with('SELECT 1')
            ->willReturn($statement);

        $driver = $this->createMock(Driver::class);
        $driver->expects($this->once())
            ->method('connect')
            ->with([])
            ->willReturn($connection);

        $this->tracer
            ->expects($this->once())
            ->method('withDetachedSpan')
            ->with(
                'DB Query',
                $this->callback('is_callable'),
                $this->callback(static fn (array $attributes): bool => $attributes['db.system'] === 'postgresql'
                    && $attributes['db.statement'] === 'SELECT 1')
            )
            ->willReturnCallback(static fn (string $name, callable $operation): mixed => $operation());

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with('DBAL statement executed', $this->callback(static fn (array $context): bool => $context['sql'] === 'SELECT 1'));

        $middleware = new DoctrineTracingMiddleware($this->tracer, $this->logger);
        $wrappedConnection = $middleware->wrap($driver)->connect([]);
        $wrappedStatement = $wrappedConnection->prepare('SELECT 1');

        self::assertSame($result, $wrappedStatement->execute());
    }

    public function testWrapDelegatesWithoutTracingWhenDisabled(): void
    {
        $result = $this->createMock(DriverResult::class);
        $statement = $this->createMock(DriverStatement::class);
        $statement->expects($this->once())
            ->method('execute')
            ->willReturn($result);

        $connection = $this->createMock(DriverConnection::class);
        $connection->method('prepare')->willReturn($statement);

        $driver = $this->createMock(Driver::class);
        $driver->method('connect')->willReturn($connection);

        $this->tracer->expects($this->never())->method('withDetachedSpan');
        $this->logger->expects($this->never())->method('debug');

        $middleware = new DoctrineTracingMiddleware($this->tracer, $this->logger, enabled: false);
        $wrappedConnection = $middleware->wrap($driver)->connect([]);
        $wrappedStatement = $wrappedConnection->prepare('SELECT 1');

        self::assertSame($result, $wrappedStatement->execute());
    }
}
