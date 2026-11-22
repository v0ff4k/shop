<?php

declare(strict_types=1);

namespace App\Infrastructure\OpenTelemetry;

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\Contrib\Otlp\SpanExporterFactory;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Trace\SamplerFactory;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;

/**
 * OpenTelemetry tracer для распределённой трассировки.
 */
class OpenTelemetryTracer
{
    private TracerInterface $tracer;
    private ?Span $currentSpan = null;

    public function __construct(string $serviceName = 'shop-application')
    {
        $tracerProvider = $this->createTracerProvider($serviceName);
        $this->tracer = $tracerProvider->getTracer($serviceName);
    }

    /**
     * Выполняет операцию в контексте span.
     *
     * @template T
     *
     * @param callable(Span): T $operation
     */
    public function withSpan(
        string $name,
        callable $operation,
        array $attributes = [],
        ?SpanKind $spanKind = null,
    ): mixed {
        $span = $this->startSpan($name, $attributes, $spanKind);
        $scope = $span->activate();

        try {
            $result = $operation($span);
            $this->endSpan(StatusCode::STATUS_OK);

            return $result;
        } catch (\Throwable $e) {
            $this->recordException($span, $e);
            $this->endSpan(StatusCode::STATUS_ERROR, $e->getMessage());

            throw $e;
        } finally {
            $scope->detach();
        }
    }

    /**
     * Выполняет операцию в отдельном span без изменения текущего active span в сервисе.
     *
     * @template T
     *
     * @param callable(): T $operation
     */
    public function withDetachedSpan(
        string $name,
        callable $operation,
        array $attributes = [],
        ?int $spanKind = null,
    ): mixed {
        $spanBuilder = $this->tracer->spanBuilder($name);

        if ($spanKind !== null) {
            $spanBuilder = $spanBuilder->setSpanKind($spanKind);
        }

        foreach ($attributes as $key => $value) {
            $spanBuilder->setAttribute($key, $value);
        }

        $span = $spanBuilder->startSpan();
        $scope = $span->activate();

        try {
            $result = $operation();
            $span->setStatus(StatusCode::STATUS_OK);
            $span->end();

            return $result;
        } catch (\Throwable $e) {
            $span->recordException($e, [
                'exception.type' => $e::class,
                'exception.message' => $e->getMessage(),
            ]);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->end();

            throw $e;
        } finally {
            $scope->detach();
        }
    }

    /**
     * Начинает новый span.
     */
    public function startSpan(
        string $name,
        array $attributes = [],
        ?int $spanKind = null,
    ): Span {
        $spanBuilder = $this->tracer->spanBuilder($name);

        if ($spanKind !== null) {
            $spanBuilder = $spanBuilder->setSpanKind($spanKind);
        }

        foreach ($attributes as $key => $value) {
            $spanBuilder->setAttribute($key, $value);
        }

        $span = $spanBuilder->startSpan();
        $this->currentSpan = $span;

        return $span;
    }

    /**
     * Добавляет атрибут к текущему span.
     */
    public function setAttribute(string $key, mixed $value): void
    {
        if ($this->currentSpan !== null) {
            $this->currentSpan->setAttribute($key, $value);
        }
    }

    /**
     * Завершает текущий span.
     */
    public function endSpan(?string $status = null, ?string $statusDescription = null): void
    {
        if ($this->currentSpan !== null) {
            if ($status !== null) {
                $this->currentSpan->setStatus($status, $statusDescription);
            }
            $this->currentSpan->end();
            $this->currentSpan = null;
        }
    }

    /**
     * Записывает исключение в span.
     */
    public function recordException(Span $span, \Throwable $exception): void
    {
        $span->recordException(
            $exception, [
                'exception.type' => $exception::class,
                'exception.message' => $exception->getMessage(),
                'exception.stacktrace' => $exception->getTraceAsString(),
            ]);
    }

    /**
     * Добавляет событие к текущему span.
     */
    public function addEvent(string $name, array $attributes = []): void
    {
        if ($this->currentSpan !== null) {
            $this->currentSpan->addEvent($name, $attributes);
        }
    }

    /**
     * Получает текущий span из контекста.
     */
    public function getCurrentSpan(): ?Span
    {
        return Span::fromContext(Context::getCurrent());
    }

    /**
     * Создаёт span для HTTP запроса.
     */
    public function startHttpSpan(
        string $method,
        string $url,
        array $headers = [],
    ): Span {
        $attributes = [
            'http.method' => $method,
            'http.url' => $url,
            'http.scheme' => parse_url($url, \PHP_URL_SCHEME) ?? 'http',
        ];

        return $this->startSpan($method.' '.parse_url($url, \PHP_URL_PATH) ?: '/', $attributes, SpanKind::KIND_CLIENT);
    }

    /**
     * Создаёт span для SQL запроса.
     */
    public function startDatabaseSpan(
        string $statement,
        string $dbSystem = 'postgresql',
        string $dbName = 'shop',
    ): Span {
        $attributes = [
            'db.system' => $dbSystem,
            'db.name' => $dbName,
            'db.statement' => $statement,
        ];

        return $this->startSpan('DB Query', $attributes, SpanKind::KIND_CLIENT);
    }

    /**
     * Создаёт span для обработки команды.
     */
    public function startCommandSpan(string $commandName): Span
    {
        $attributes = [
            'messaging.system' => 'symfony_messenger',
            'messaging.destination' => 'commands',
            'command.name' => $commandName,
        ];

        return $this->startSpan('Command: '.$commandName, $attributes, SpanKind::KIND_CONSUMER);
    }

    /**
     * Создаёт span для события домена.
     */
    public function startEventSpan(string $eventName): Span
    {
        $attributes = [
            'messaging.system' => 'symfony_messenger',
            'messaging.destination' => 'events',
            'event.name' => $eventName,
        ];

        return $this->startSpan('Event: '.$eventName, $attributes, SpanKind::KIND_CONSUMER);
    }

    /**
     * Внедряет контекст в заголовки для propagation.
     */
    public function injectContext(array &$headers): void
    {
        Globals::propagator()->inject($headers, Context::getCurrent());
    }

    /**
     * Извлекает контекст из заголовков для propagation.
     */
    public function extractContext(array $headers): ContextInterface
    {
        return Globals::propagator()->extract($headers);
    }

    /**
     * Закрывает tracer provider.
     */
    public function shutdown(): void
    {
        // Shutdown handled by SDK
    }

    /**
     * Создаёт tracer provider с OTLP exporter.
     *
     * Конфигурация exporter'а полностью через env-переменные (без хардкода в коде — п.41 senior-чеклиста):
     *  - OTEL_EXPORTER_OTLP_ENDPOINT    — адрес коллектора (например http://jaeger:4317 для gRPC или http://jaeger:4318 для HTTP)
     *  - OTEL_EXPORTER_OTLP_PROTOCOL    — протокол: "grpc" (по умолчанию) или "http/protobuf"
     *  - OTEL_EXPORTER_OTLP_HEADERS     — дополнительные заголовки (опционально)
     *
     * SpanExporterFactory сам читает эти переменные и подбирает корректный транспорт
     * (GrpcTransport для grpc, PsrTransport для http/protobuf), что решает проблему
     * "Received HTTP/0.9 when not allowed" при попытке слать HTTP в gRPC-порт.
     */
    private function createTracerProvider(string $serviceName): TracerProvider
    {
        $exporterFactory = new SpanExporterFactory();
        $exporter = $exporterFactory->create();

        $attributes = Attributes::create([
            ResourceAttributes::SERVICE_NAME => $serviceName,
            ResourceAttributes::SERVICE_VERSION => '1.0.0',
        ]);
        $resource = ResourceInfo::create($attributes);

        return new TracerProvider(
            new SimpleSpanProcessor($exporter),
            (new SamplerFactory())->create(),
            $resource
        );
    }
}
