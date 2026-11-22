<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Интерфейс для команд с поддержкой идемпотентности.
 *
 * Команды, реализующие этот интерфейс, могут иметь уникальный ключ
 * для предотвращения повторного выполнения
 */
interface IdempotentCommandInterface
{
    /**
     * Получить ключ идемпотентности команды.
     *
     * @return string|null Null если ключ не установлен
     */
    public function getIdempotencyKey(): ?string;
}
