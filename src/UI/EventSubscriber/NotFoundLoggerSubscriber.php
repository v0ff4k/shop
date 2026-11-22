<?php

declare(strict_types=1);

namespace App\UI\EventSubscriber;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Подписчик для логирования всех 404 ошибок
 * Особенно важен для отслеживания битых ссылок на изображения и файлы.
 */
final class NotFoundLoggerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $notFoundLogger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onNotFound', 0],
        ];
    }

    public function onNotFound(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Логируем только 404 ошибки
        if (!$exception instanceof NotFoundHttpException) {
            return;
        }

        $request = $event->getRequest();
        $uri = $request->getUri();
        $path = $request->getPathInfo();
        $referer = $request->headers->get('referer', 'N/A');
        $userAgent = $request->headers->get('user-agent', 'N/A');

        // Определяем тип ресурса
        $resourceType = $this->getResourceType($path);

        $context = [
            'uri' => $uri,
            'path' => $path,
            'method' => $request->getMethod(),
            'referer' => $referer,
            'user_agent' => $userAgent,
            'resource_type' => $resourceType,
            'query' => $request->query->all(),
            'ip' => $request->getClientIp(),
        ];

        // Для изображений и файлов логируем как warning (битые ссылки)
        if (\in_array($resourceType, ['image', 'file', 'font', 'stylesheet', 'script'], true)) {
            $this->notFoundLogger->warning(
                'Broken link: {resource_type} not found - {path} (referer: {referer})',
                $context
            );
        } else {
            // Для страниц логируем как info
            $this->notFoundLogger->info(
                'Page not found: {path} (referer: {referer})',
                $context
            );
        }
    }

    /**
     * Определить тип ресурса по расширению.
     */
    private function getResourceType(string $path): string
    {
        $extension = strtolower(pathinfo($path, \PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'bmp' => 'image',
            'css' => 'stylesheet',
            'js', 'mjs' => 'script',
            'woff', 'woff2', 'ttf', 'eot', 'otf' => 'font',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar' => 'file',
            'xml', 'json' => 'data',
            default => 'page',
        };
    }
}
