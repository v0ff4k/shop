<?php

declare(strict_types=1);

namespace App\Tests\UI\EventSubscriber;

use App\Infrastructure\OpenTelemetry\OpenTelemetryTracer;
use App\UI\EventSubscriber\OpenTelemetrySubscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class OpenTelemetrySubscriberTest extends TestCase
{
    private OpenTelemetryTracer&MockObject $tracer;
    private OpenTelemetrySubscriber $subscriber;

    protected function setUp(): void
    {
        $this->tracer = $this->createMock(OpenTelemetryTracer::class);
        $this->subscriber = new OpenTelemetrySubscriber($this->tracer);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = OpenTelemetrySubscriber::getSubscribedEvents();

        $this->assertArrayHasKey('kernel.request', $events);
        $this->assertArrayHasKey('kernel.controller', $events);
        $this->assertArrayHasKey('kernel.response', $events);
        $this->assertArrayHasKey('kernel.exception', $events);
    }

    public function testOnKernelRequestForMainRequest(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/test', 'REQUEST_METHOD' => 'GET']);

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->tracer
            ->expects($this->once())
            ->method('startHttpSpan');

        $this->subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestForSubRequest(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $this->tracer
            ->expects($this->never())
            ->method('startHttpSpan');

        $this->subscriber->onKernelRequest($event);
    }

    public function testOnKernelController(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/test', 'REQUEST_METHOD' => 'GET']);

        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $this->subscriber->onKernelRequest($requestEvent);

        $controller = new class {
            public function __invoke(): Response
            {
                return new Response();
            }
        };

        $event = new ControllerEvent($kernel, $controller, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->tracer
            ->expects($this->once())
            ->method('setAttribute')
            ->with('controller', $this->stringContains('__invoke'));

        $this->subscriber->onKernelController($event);
    }

    public function testOnKernelResponse(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/test', 'REQUEST_METHOD' => 'GET']);

        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $this->subscriber->onKernelRequest($requestEvent);

        $response = new Response('', 200);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->tracer
            ->expects($this->once())
            ->method('setAttribute')
            ->with('http.status_code', 200);

        $this->subscriber->onKernelResponse($event);
    }
}
