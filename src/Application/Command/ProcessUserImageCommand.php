<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Команда асинхронной обработки аватара пользователя.
 */
final class ProcessUserImageCommand
{
    public function __construct(
        public readonly string $userId,
        public readonly string $filePath,
        public readonly string $originalName,
    ) {
    }
}
