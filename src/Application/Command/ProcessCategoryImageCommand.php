<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Команда асинхронной обработки изображения категории.
 */
final class ProcessCategoryImageCommand
{
    public function __construct(
        public readonly string $categoryId,
        public readonly string $filePath,
        public readonly string $originalName,
    ) {
    }
}
