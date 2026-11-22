<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Команда для удаления продукта.
 */
final class DeleteProductCommand
{
    public function __construct(
        public readonly int $productId
    ) {
    }
}
