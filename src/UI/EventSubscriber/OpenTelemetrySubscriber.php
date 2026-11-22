<?php

declare(strict_types=1);

namespace App\UI\EventSubscriber;

use App\Infrastructure\OpenTelemetry\OpenTelemetryTracer;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\ScopeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * OpenTelemetry subscriber для автоматической трассировки HTTP запросов.
 */
final class OpenTelemetrySubscriber implements EventSubscriberInterface
{
    private ?Span $requestSpan = null;
    private ?ScopeInterface $parentScope = null;
    private ?ScopeInterface $spanScope = null;

    public function __construct(
        private readonly OpenTelemetryTracer $tracer
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
            KernelEvents::CONTROLLER => ['onKernelController', 10],
            KernelEvents::RESPONSE => ['onKernelResponse', -10],
            KernelEvents::EXCEPTION => ['onKernelException', -10],
        ];
    }

    /**
     * Начинаем span для каждого запроса.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $headers = $request->headers->all();
        $context = $this->tracer->extractContext($headers);

        $this->parentScope = $context->activate();

        $this->requestSpan = $this->tracer->startHttpSpan(
            method: $request->getMethod(),
            url: $request->getUri(),
        );
        $this->spanScope = $this->requestSpan->activate();

        // Добавляем атрибуты запроса
        $this->tracer->setAttribute('http.route', $request->getPathInfo());
        $this->tracer->setAttribute('http.user_agent', $request->headers->get('User-Agent'));
    }

    /**
     * Добавляем информацию о контроллере.
     */
    public function onKernelController(ControllerEvent $event): void
    {
        if ($this->requestSpan === null) {
            return;
        }

        $controller = $event->getController();

        if (\is_array($controller)) {
            $controllerName = \get_class($controller[0]).'::'.$controller[1];
        } elseif (\is_object($controller)) {
            $controllerName = $controller::class.'::__invoke';
        } else {
            $controllerName = 'unknown';
        }

        $this->tracer->setAttribute('controller', $controllerName);
        $this->tracer->addEvent('controller_executed');
    }

    /**
     * Завершаем span при успешном ответе.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if ($this->requestSpan === null) {
            return;
        }

        if (!$event->isMainRequest()) {
            return;
        }

        $response = $event->getResponse();

        $this->tracer->setAttribute('http.status_code', $response->getStatusCode());
        $this->tracer->addEvent('response_sent');

        $this->tracer->endSpan(StatusCode::STATUS_OK);
        $this->cleanupTraceContext();
    }

    /**
     * Обрабатываем исключения.
     */
    public function onKernelException(ExceptionEvent $event): void
    {
        if ($this->requestSpan === null) {
            return;
        }

        $exception = $event->getThrowable();

        $this->tracer->recordException($this->requestSpan, $exception);
        $this->tracer->setAttribute('http.status_code', 500);

        $this->tracer->endSpan(StatusCode::STATUS_ERROR, $exception->getMessage());
        $this->cleanupTraceContext();
    }

    private function cleanupTraceContext(): void
    {
        $this->requestSpan = null;

        if ($this->spanScope !== null) {
            $this->spanScope->detach();
            $this->spanScope = null;
        }

        if ($this->parentScope !== null) {
            $this->parentScope->detach();
            $this->parentScope = null;
        }
    }
}
