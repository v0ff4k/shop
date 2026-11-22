<?php

declare(strict_types=1);

namespace App\Tests\UI\EventListener;

use App\Infrastructure\Prometheus\PrometheusStorageInterface;
use App\UI\EventListener\PrometheusMetricsListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class PrometheusMetricsListenerTest extends TestCase
{
    private PrometheusStorageInterface&MockObject $storage;
    private PrometheusMetricsListener $listener;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(PrometheusStorageInterface::class);
        $this->listener = new PrometheusMetricsListener($this->storage);
    }

    public function testOnRequestIncrementsRequestCounter(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/test', 'REQUEST_METHOD' => 'GET']);
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->storage
            ->expects($this->once())
            ->method('incrementCounter')
            ->with('http_requests_started_total');

        $this->listener->onRequest($event);
    }

    public function testOnRequestForSubRequestDoesNothing(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $this->storage
            ->expects($this->never())
            ->method('incrementCounter');

        $this->listener->onRequest($event);
    }

    public function testOnResponseRecordsLatency(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/test']);
        $response = new Response('', 200);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->storage
            ->expects($this->once())
            ->method('incrementCounter')
            ->with('http_requests_total', $this->isType('array'));

        $this->storage
            ->expects($this->once())
            ->method('observeHistogram')
            ->with('http_request_duration_seconds');

        $this->listener->onResponse($event);
    }

    public function testOnExceptionRecordsErrorMetrics(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request([], [], [], [], [], ['REQUEST_URI' => '/test', 'REQUEST_METHOD' => 'POST']);
        $calls = [];
        $event = new ExceptionEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new \RuntimeException('boom')
        );

        $this->storage
            ->expects($this->exactly(3))
            ->method('incrementCounter')
            ->willReturnCallback(static function (string $name, array $labels = [], float $value = 1.0) use (&$calls): void {
                $calls[] = $name;
            });

        $this->storage
            ->expects($this->once())
            ->method('observeHistogram')
            ->with('http_request_duration_seconds', $this->isType('float'), $this->isType('array'));

        $this->listener->onRequest(new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST));
        $this->listener->onException($event);

        self::assertSame(
            [
                'http_requests_started_total',
                'http_exceptions_total',
                'http_requests_total',
            ],
            $calls
        );
    }

    public function testOnResponseForSubRequestDoesNothing(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = new Request();
        $response = new Response();
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $this->storage
            ->expects($this->never())
            ->method('observeHistogram');

        $this->listener->onResponse($event);
    }
}
