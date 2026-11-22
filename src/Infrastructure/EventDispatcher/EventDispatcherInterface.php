<?php

declare(strict_types=1);

namespace App\Infrastructure\EventDispatcher;

/**
 * Интерфейс для публикации доменных событий.
 */
interface EventDispatcherInterface
{
    /**
     * Опубликовать событие.
     */
    public function dispatch(object $event): void;
}
