<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

/**
 * DTO для запроса добавления в корзину.
 */
final class AddToCartRequest
{
    public function __construct(
        public readonly int $productId,
        public readonly int $quantity = 1
    ) {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be at least 1');
        }
    }
}
