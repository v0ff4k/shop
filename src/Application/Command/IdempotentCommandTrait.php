<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Trait для добавления поддержки idempotency key в команды.
 */
trait IdempotentCommandTrait
{
    private readonly ?string $idempotencyKey;

    public function getIdempotencyKey(): ?string
    {
        return $this->idempotencyKey ?? null;
    }
}
