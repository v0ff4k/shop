<?php

declare(strict_types=1);

namespace App\Infrastructure\Idempotency;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;

/**
 * Middleware для обеспечения идемпотентности обработки команд.
 */
final class IdempotencyMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly IdempotencyCheckerInterface $idempotencyChecker
    ) {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        $message = $envelope->getMessage();

        // Проверяем, есть ли у сообщения ключ идемпотентности
        $idempotencyKey = $this->extractIdempotencyKey($message);

        if ($idempotencyKey === null) {
            // Нет ключа идемпотентности - пропускаем
            return $stack->next()->handle($envelope, $stack);
        }

        // Проверяем, обрабатывалась ли уже команда
        if ($this->idempotencyChecker->isDuplicate($idempotencyKey)) {
            // Команда уже обрабатывалась - возвращаем предыдущий результат
            $previousResult = $this->idempotencyChecker->getProcessedResult($idempotencyKey);

            // Возвращаем тот же результат, что и при предыдущей обработке
            return new Envelope(
                $message, [
                    new HandledStamp($previousResult, 'Idempotent result'),
                ]
            );
        }

        // Обрабатываем команду
        $envelope = $stack->next()->handle($envelope, $stack);

        // Сохраняем результат для будущих запросов
        $handledStamp = $envelope->last(HandledStamp::class);
        if ($handledStamp !== null) {
            $this->idempotencyChecker->markAsProcessed(
                $idempotencyKey,
                $handledStamp->getResult()
            );
        }

        return $envelope;
    }

    /**
     * Извлечь ключ идемпотентности из сообщения.
     */
    private function extractIdempotencyKey(object $message): ?string
    {
        // Если сообщение имеет метод getIdempotencyKey()
        if (method_exists($message, 'getIdempotencyKey')) {
            return $message->getIdempotencyKey();
        }

        // Если сообщение имеет свойство idempotencyKey
        if (property_exists($message, 'idempotencyKey')) {
            return $message->idempotencyKey;
        }

        return null;
    }
}
