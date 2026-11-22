<?php

declare(strict_types=1);

namespace App\UI\Request;

/**
 * Базовый DTO с поддержкой idempotency key.
 *
 * Наследники могут принимать X-Idempotency-Key заголовок
 * для предотвращения повторных запросов
 */
abstract class IdempotentRequestDto
{
    /**
     * Ключ идемпотентности из заголовка X-Idempotency-Key.
     */
    public readonly ?string $idempotencyKey;

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey ?? null;
    }

    protected function setIdempotencyKey(?string $key): void
    {
        if ($key !== null && $key !== '') {
            $this->idempotencyKey = $key;
        }
    }
}
