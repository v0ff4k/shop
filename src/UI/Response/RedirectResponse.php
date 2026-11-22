<?php

declare(strict_types=1);

namespace App\UI\Response;

/**
 * Обёртка для редиректа с flash сообщениями
 * Преобразуется в реальный RedirectResponse в ControllerResponseSubscriber.
 */
final class RedirectResponse
{
    public function __construct(
        public readonly string $route,
        public readonly array $parameters = [],
        public readonly string $flashType = 'success',
        public readonly string $flashMessage = ''
    ) {
    }
}
