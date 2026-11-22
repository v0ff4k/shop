<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Команда для создания продукта (CQRS - Command side).
 */
final class CreateProductCommand
{
    public function __construct(
        public readonly string $title,
        public readonly string $price,
        public readonly string $description,
        public readonly int $categoryId,
        public readonly int $available = 0,
        /** @var array<array{path: string, original_name: string, position: int}>|null */
        public readonly ?array $imageData = null
    ) {
    }
}
