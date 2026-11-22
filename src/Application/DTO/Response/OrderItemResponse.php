<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

use App\Domain\Order\Entity\OrderItem;

/**
 * DTO для ответа элемента заказа.
 */
final class OrderItemResponse
{
    public function __construct(
        public readonly int $id,
        public readonly int $productId,
        public readonly string $productTitle,
        public readonly int $quantity,
        public readonly float $price,
        public readonly float $total
    ) {
    }

    public static function fromEntity(OrderItem $item): self
    {
        $product = $item->getProduct();

        return new self(
            $item->getId(),
            $product?->getId() ?? 0,
            $product?->getTitle() ?? 'Unknown',
            $item->getQuantity(),
            (float) $item->getPrice(),
            (float) $item->getTotal()
        );
    }
}
