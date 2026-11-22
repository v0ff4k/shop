<?php

declare(strict_types=1);

namespace App\Application\Query\DTO;

/**
 * DTO для элемента корзины
 * Избегает проблем с циклическими ссылками Doctrine entity.
 */
final class CartItemDTO
{
    public function __construct(
        public readonly int $productId,
        public readonly string $productTitle,
        public readonly string $productSlug,
        public readonly string $productPrice,
        public readonly int $productAvailable,
        public readonly ?string $productImage = null,
        public readonly int $quantity = 1,
        public readonly float $subtotal = 0.0,
    ) {
    }
}
