<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Persistence\Middleware;

use App\Infrastructure\OpenTelemetry\OpenTelemetryTracer;
use App\Infrastructure\Persistence\Middleware\DoctrineTracingMiddleware;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class DoctrineTracingMiddlewareTest extends TestCase
{
    private OpenTelemetryTracer&MockObject $tracer;
    private LoggerInterface&MockObject $logger;
    private Driver&MockObject $driver;
    private DriverConnection&MockObject $connection;
    private Statement&MockObject $statement;
    private Result&MockObject $result;

    protected function setUp(): void
    {
        $this->tracer = $this->createMock(OpenTelemetryTracer::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->driver = $this->createMock(Driver::class);
        $this->connection = $this->createMock(DriverConnection::class);
        $this->statement = $this->createMock(Statement::class);
        $this->result = $this->createMock(Result::class);
    }

    public function testExecuteWrapsSqlInDetachedSpanAndLogsQuery(): void
    {
        $middleware = new DoctrineTracingMiddleware($this->tracer, $this->logger, true, true);

        $this->driver
            ->expects($this->once())
            ->method('connect')
            ->with([])
            ->willReturn($this->connection);

        $this->connection
            ->expects($this->once())
            ->method('prepare')
            ->with('SELECT * FROM products WHERE id = ?')
            ->willReturn($this->statement);

        $this->statement
            ->expects($this->once())
            ->method('execute')
            ->willReturn($this->result);

        $this->tracer
            ->expects($this->once())
            ->method('withDetachedSpan')
            ->with(
                'DB Query',
                $this->isType('callable'),
                [
                    'db.system' => 'postgresql',
                    'db.name' => 'shop',
                    'db.statement' => 'SELECT * FROM products WHERE id = ?',
                ]
            )
            ->willReturnCallback(
                static function (
                    string $name,
                    callable $operation,
                    array $attributes = [],
                    ?int $spanKind = null
                ): Result {
                    self::assertSame('DB Query', $name);
                    self::assertSame('postgresql', $attributes['db.system']);
                    self::assertSame('shop', $attributes['db.name']);

                    return $operation();
                }
            );

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with(
                'DBAL statement executed',
                $this->callback(static function (array $context): bool {
                    return isset($context['duration_ms'], $context['sql'])
                        && $context['sql'] === 'SELECT * FROM products WHERE id = ?';
                })
            );

        $wrappedDriver = $middleware->wrap($this->driver);
        $wrappedConnection = $wrappedDriver->connect([]);
        $wrappedStatement = $wrappedConnection->prepare('SELECT * FROM products WHERE id = ?');

        self::assertSame($this->result, $wrappedStatement->execute());
    }
}
