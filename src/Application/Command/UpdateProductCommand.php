<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Команда для обновления продукта.
 */
final class UpdateProductCommand
{
    public function __construct(
        public readonly int $productId,
        public readonly ?string $title = null,
        public readonly ?string $price = null,
        public readonly ?string $description = null,
        public readonly ?int $categoryId = null,
        public readonly ?int $available = null
    ) {
    }
}
