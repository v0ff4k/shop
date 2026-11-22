<?php

declare(strict_types=1);

namespace App\UI\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Twig\Error\Error as TwigError;

/**
 * Подписчик для логирования всех Twig ошибок и исключений
 * Гарантирует что все ошибки записываются в логи.
 */
final class TwigErrorLoggerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $twigLogger,
        private readonly LoggerInterface $errorLogger,
        private readonly LoggerInterface $criticalLogger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onException', 10],
        ];
    }

    public function onException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        $context = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'route' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'controller' => $request->attributes->get('_controller', 'N/A'),
            'query' => $request->query->all(),
            'request' => $request->request->all(),
        ];

        // Логируем Twig ошибки
        if ($exception instanceof TwigError || str_contains($exception->getMessage(), 'Twig')) {
            $this->twigLogger->error('Twig Error: {message}', $context);
        }

        // Логируем все ошибки уровня error и выше
        $this->errorLogger->error('Error: {message}', $context);

        // Логируем критические ошибки
        if ($exception instanceof \Error || $exception instanceof \TypeError) {
            $this->criticalLogger->critical('Critical Error: {message}', $context);
        }
    }
}
