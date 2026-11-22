<?php

declare(strict_types=1);

namespace App\Infrastructure\Idempotency;

use App\Application\Idempotency\IdempotencyCheckerInterface as ApplicationIdempotencyCheckerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Интерфейс для проверки идемпотентности команд (Infrastructure слой)
 * Расширяет Application интерфейс дополнительными методами для HTTP.
 */
interface IdempotencyCheckerInterface extends ApplicationIdempotencyCheckerInterface
{
    /**
     * Извлечь ключ идемпотентности из запроса.
     */
    public function extractKeyFromRequest(Request $request): ?string;
}
