<?php

declare(strict_types=1);

namespace App\UI\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * HTTP Cache subscriber для добавления cache headers.
 */
final class HttpCacheSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly int $defaultMaxAge = 3600,
        private readonly int $sharedMaxAge = 3600,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', 10],
        ];
    }

    /**
     * Добавляем cache headers к ответам
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Не кэшируем POST, PUT, DELETE запросы
        if (!\in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return;
        }

        // Не кэшируем API запросы (stateless)
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        // Не кэшируем админ панель
        if (str_starts_with($request->getPathInfo(), '/admin/')) {
            return;
        }

        // Если ответ уже имеет cache headers - не трогаем
        if ($response->headers->hasCacheControlDirective('no-cache')) {
            return;
        }

        // Добавляем cache headers для публичных ресурсов
        $response->setPublic();
        $response->setMaxAge($this->defaultMaxAge);
        $response->setSharedMaxAge($this->sharedMaxAge);

        // Добавляем ETag
        $response->setEtag(md5($response->getContent()));

        // Добавляем Vary headers
        $response->setVary(['Accept', 'Accept-Language'], false);
    }
}
