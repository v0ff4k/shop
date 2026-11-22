<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Команда для асинхронной обработки изображения продукта.
 */
final class ProcessProductImageCommand
{
    public function __construct(
        public readonly string $productId,
        public readonly string $filePath,
        public readonly string $originalName,
        public readonly int $position = 0
    ) {
    }
}
