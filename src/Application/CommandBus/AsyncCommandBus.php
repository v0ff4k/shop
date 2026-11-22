<?php

declare(strict_types=1);

namespace App\Application\CommandBus;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Асинхронная реализация шины команд через Symfony Messenger.
 */
final class AsyncCommandBus implements CommandBusInterface
{
    public function __construct(
        private readonly MessageBusInterface $asyncBus
    ) {
    }

    /**
     * Отправить команду в очередь без ожидания.
     */
    public function dispatchAsync(object $command): void
    {
        $this->asyncBus->dispatch($command);
    }

    public function dispatch(object $command): mixed
    {
        // Для асинхронной обработки просто отправляем в очередь
        // Результат будет null, так как обработка происходит позже
        $this->asyncBus->dispatch(
            Envelope::wrap(
                $command,
                [
                    new DispatchAfterCurrentBusStamp(),
                ]
            )
        );

        return null;
    }
}
