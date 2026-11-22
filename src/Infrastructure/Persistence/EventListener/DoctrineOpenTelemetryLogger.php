<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\EventListener;

use App\Infrastructure\OpenTelemetry\OpenTelemetryTracer;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\StatusCode;

/**
 * OpenTelemetry logger для Doctrine SQL запросов.
 *
 * ВНИМАНИЕ: В DBAL 4.x интерфейс SQLLogger удалён.
 * Этот класс требует адаптации для работы с DBAL 4 через Middleware.
 * Пока отключён в services.yaml.
 */
final class DoctrineOpenTelemetryLogger
{
    private ?Span $currentSpan = null;
    private array $queryStack = [];

    public function __construct(
        private readonly OpenTelemetryTracer $tracer,
        private readonly float $slowQueryThreshold = 0.1,
    ) {
    }

    /**
     * Записать SQL запрос в tracing.
     */
    public function logQuery(string $sql, ?array $params = null): void
    {
        $span = $this->tracer->startDatabaseSpan(
            statement: $this->normalizeQuery($sql),
            dbSystem: 'postgresql',
            dbName: 'shop'
        );

        if ($params !== null && $params !== []) {
            $span->setAttribute('db.params', json_encode($params, \JSON_THROW_ON_ERROR));
        }

        $scope = $span->activate();
        $this->queryStack[] = [$span, $scope, microtime(true)];
        $this->currentSpan = $span;
    }

    /**
     * Завершить tracing запроса.
     */
    public function stopQuery(): void
    {
        if ($this->queryStack === []) {
            return;
        }

        [$span, $scope, $startTime] = array_pop($this->queryStack);
        $scope->detach();

        $executionTime = microtime(true) - $startTime;
        $span->setAttribute('db.execution_time', (string) $executionTime);

        if ($executionTime > $this->slowQueryThreshold) {
            $span->setAttribute('db.slow_query', true);
        }

        $span->addEvent('query_executed', ['execution_time' => $executionTime]);
        $this->tracer->endSpan(StatusCode::STATUS_OK);

        $this->currentSpan = $this->queryStack !== [] ? end($this->queryStack)[0] : null;
    }

    public function getCurrentSpan(): ?Span
    {
        return $this->currentSpan;
    }

    private function normalizeQuery(string $sql): string
    {
        $sql = preg_replace('/\s+/', ' ', trim($sql));

        return mb_substr($sql, 0, 100);
    }
}
