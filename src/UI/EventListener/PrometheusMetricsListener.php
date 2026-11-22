<?php

declare(strict_types=1);

namespace App\UI\EventListener;

use App\Infrastructure\Prometheus\PrometheusStorageInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class PrometheusMetricsListener implements EventSubscriberInterface
{
    private ?float $requestStartTime = null;

    public function __construct(
        private readonly PrometheusStorageInterface $storage
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onRequest',
            KernelEvents::RESPONSE => 'onResponse',
            KernelEvents::EXCEPTION => 'onException',
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $this->requestStartTime = microtime(true);

        $this->storage->incrementCounter(
            'http_requests_started_total',
            [
                'method' => $request->getMethod(),
                'path' => $this->normalizePath($request->getPathInfo()),
            ]
        );
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $this->finalizeRequestMetrics(
            $event->getRequest(),
            $event->getResponse()->getStatusCode()
        );
    }

    public function onException(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $this->storage->incrementCounter(
            'http_exceptions_total',
            [
                'method' => $request->getMethod(),
                'path' => $this->normalizePath($request->getPathInfo()),
                'exception' => $event->getThrowable()::class,
            ]
        );

        $this->finalizeRequestMetrics($request, 500);
    }

    private function finalizeRequestMetrics(Request $request, int $statusCode): void
    {
        $duration = $this->requestStartTime === null ? 0.0 : microtime(true) - $this->requestStartTime;
        $labels = [
            'method' => $request->getMethod(),
            'path' => $this->normalizePath($request->getPathInfo()),
            'status' => (string) $statusCode,
        ];

        $this->storage->incrementCounter('http_requests_total', $labels);
        $this->storage->observeHistogram('http_request_duration_seconds', $duration, $labels);
        $this->requestStartTime = null;
    }

    private function normalizePath(string $path): string
    {
        $normalized = preg_replace('/\/\d+/', '/{id}', $path);

        return $normalized ?: '/';
    }
}
