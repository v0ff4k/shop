<?php

declare(strict_types=1);

namespace App\Application\CommandBus;

use App\Application\Command\IdempotentCommandInterface;
use App\Application\Idempotency\DuplicateCommandException;
use App\Application\Idempotency\IdempotencyCheckerInterface;
use Symfony\Component\Messenger\Exception\HandlerFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Синхронная реализация шины команд через Symfony Messenger.
 */
final class SyncCommandBus implements CommandBusInterface
{
    public function __construct(
        private readonly MessageBusInterface $commandBus,
        private readonly ?IdempotencyCheckerInterface $idempotencyChecker = null
    ) {
    }

    public function dispatch(object $command): mixed
    {
        // Проверяем идемпотентность если команда поддерживает
        if ($this->idempotencyChecker !== null && $command instanceof IdempotentCommandInterface) {
            $idempotencyKey = $command->getIdempotencyKey();

            if ($idempotencyKey !== null) {
                // Проверяем, не выполнялась ли уже эта команда
                if ($this->idempotencyChecker->isDuplicate($idempotencyKey)) {
                    $cachedResult = $this->idempotencyChecker->getProcessedResult($idempotencyKey);

                    if ($cachedResult !== null) {
                        return $cachedResult;
                    }

                    throw new DuplicateCommandException(\sprintf('Command with idempotency key "%s" is already being processed', $idempotencyKey));
                }
            }
        }

        try {
            // INSERT INTO messenger_messages (body, headers, queue_name, c...
            $envelope = $this->commandBus->dispatch($command);

            $handledStamp = $envelope->last(HandledStamp::class);

            if ($handledStamp === null) {
                return null;
            }

            $result = $handledStamp->getResult();

            // Сохраняем результат для идемпотентности
            if ($this->idempotencyChecker !== null && $command instanceof IdempotentCommandInterface) {
                $idempotencyKey = $command->getIdempotencyKey();
                if ($idempotencyKey !== null) {
                    $this->idempotencyChecker->markAsProcessed($idempotencyKey, $result);
                }
            }

            return $result;
        } catch (HandlerFailedException $e) {
            // Разворачиваем оригинальное исключение
            throw $e->getPrevious() ?? $e;
        }
    }
}
