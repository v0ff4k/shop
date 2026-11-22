<?php

declare(strict_types=1);

namespace App\Application\Command\Cart;

/**
 * Команда для добавления товара в корзину.
 */
final class AddToCartCommand
{
    public function __construct(
        public readonly int $productId,
        public readonly int $quantity,
    ) {
    }
}
