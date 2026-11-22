<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging\Handler;

use App\Domain\Order\Event\OrderCreatedEvent;
use App\Domain\Shop\Event\ProductCreatedEvent;
use App\Domain\User\Event\UserRegisteredEvent;
use App\Infrastructure\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Обработчик сообщений для публикации доменных событий.
 */
#[AsMessageHandler]
final class DomainEventPublisherHandler
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function __invoke(ProductCreatedEvent|OrderCreatedEvent|UserRegisteredEvent $event): void
    {
        $this->eventDispatcher->dispatch($event);
    }
}
