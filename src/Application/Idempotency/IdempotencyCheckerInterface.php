<?php

declare(strict_types=1);

namespace App\Application\Idempotency;

/**
 * Интерфейс для проверки идемпотентности команд.
 *
 * Гарантирует, что одна и та же команда не будет выполнена дважды
 */
interface IdempotencyCheckerInterface
{
    /**
     * Проверить, является ли команда дубликатом
     *
     * @param string $idempotencyKey Уникальный ключ идемпотентности
     *
     * @return bool true если команда уже выполнялась (дубликат)
     */
    public function isDuplicate(string $idempotencyKey): bool;

    /**
     * Отметить команду как выполненную.
     *
     * @param string $idempotencyKey Уникальный ключ идемпотентности
     * @param mixed $result Результат выполнения команды
     * @param int $ttl Время жизни в секундах (по умолчанию 24 часа)
     */
    public function markAsProcessed(string $idempotencyKey, mixed $result, int $ttl = 86400): void;

    /**
     * Получить результат ранее выполненной команды.
     *
     * @param string $idempotencyKey Уникальный ключ идемпотентности
     *
     * @return mixed|null Результат выполнения или null
     */
    public function getProcessedResult(string $idempotencyKey): mixed;
}
