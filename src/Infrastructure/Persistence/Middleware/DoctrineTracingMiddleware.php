<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Middleware;

use App\Infrastructure\OpenTelemetry\OpenTelemetryTracer;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Middleware as DriverMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Psr\Log\LoggerInterface;

/**
 * Class DoctrineTracingMiddleware.
 *
 * WARN SLOW DOWN EVERY QUERY !!!!
 */
final class DoctrineTracingMiddleware implements DriverMiddleware
{
    public function __construct(
        private readonly OpenTelemetryTracer $tracer,
        private readonly LoggerInterface $logger,
        private readonly bool $enabled = true,
        private readonly bool $logQueries = true,
    ) {
    }

    public function wrap(Driver $driver): Driver
    {
        return new class($driver, $this->tracer, $this->logger, $this->enabled, $this->logQueries) extends AbstractDriverMiddleware {
            public function __construct(
                Driver $wrappedDriver,
                private readonly OpenTelemetryTracer $tracer,
                private readonly LoggerInterface $logger,
                private readonly bool $enabled,
                private readonly bool $logQueries,
            ) {
                parent::__construct($wrappedDriver);
            }

            public function connect(#[\SensitiveParameter] array $params): DriverConnection
            {
                $connection = parent::connect($params);

                return new class($connection, $this->tracer, $this->logger, $this->enabled, $this->logQueries) extends AbstractConnectionMiddleware {
                    public function __construct(
                        DriverConnection $wrappedConnection,
                        private readonly OpenTelemetryTracer $tracer,
                        private readonly LoggerInterface $logger,
                        private readonly bool $enabled,
                        private readonly bool $logQueries,
                    ) {
                        parent::__construct($wrappedConnection);
                    }

                    public function prepare(string $sql): Statement
                    {
                        $statement = parent::prepare($sql);

                        return new class($statement, $sql, $this->tracer, $this->logger, $this->enabled, $this->logQueries) extends AbstractStatementMiddleware {
                            public function __construct(
                                Statement $wrappedStatement,
                                private readonly string $sql,
                                private readonly OpenTelemetryTracer $tracer,
                                private readonly LoggerInterface $logger,
                                private readonly bool $enabled,
                                private readonly bool $logQueries,
                            ) {
                                parent::__construct($wrappedStatement);
                            }

                            public function execute(): Result
                            {
                                if (!$this->enabled) {
                                    return parent::execute();
                                }

                                $startedAt = microtime(true);

                                return $this->tracer->withDetachedSpan(
                                    name: 'DB Query',
                                    operation: function () use ($startedAt): Result {
                                        try {
                                            $result = parent::execute();
                                            if ($this->logQueries) {
                                                $this->logger->debug('DBAL statement executed', [
                                                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                                                    'sql' => $this->normalize($this->sql),
                                                ]);
                                            }

                                            return $result;
                                        } catch (\Throwable $e) {
                                            if ($this->logQueries) {
                                                $this->logger->warning('DBAL statement failed', [
                                                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                                                    'sql' => $this->normalize($this->sql),
                                                    'error' => $e->getMessage(),
                                                ]);
                                            }

                                            throw $e;
                                        }
                                    },
                                    attributes: [
                                        'db.system' => 'postgresql',
                                        'db.name' => 'shop',
                                        'db.statement' => $this->normalize($this->sql),
                                    ]
                                );
                            }

                            private function normalize(string $sql): string
                            {
                                $sql = preg_replace('/\s+/', ' ', trim($sql)) ?? $sql;

                                return mb_substr($sql, 0, 500);
                            }
                        };
                    }
                };
            }
        };
    }
}
