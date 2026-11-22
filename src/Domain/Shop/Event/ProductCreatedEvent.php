<?php

declare(strict_types=1);

namespace App\Domain\Shop\Event;

use App\Domain\Shop\Entity\Product;

/**
 * Событие: продукт создан.
 */
final class ProductCreatedEvent
{
    public function __construct(
        public readonly ?int $productId,
        public readonly string $title,
        public readonly string $slug,
        public readonly float $price,
        public readonly ?int $categoryId,
        public readonly \DateTimeImmutable $occurredOn,
    ) {
    }

    public static function fromProduct(Product $product): self
    {
        return new self(
            productId: $product->getId(),
            title: $product->getTitle(),
            slug: $product->getSlug(),
            price: (float) $product->getPrice(),
            categoryId: $product->getCategory()?->getId(),
            occurredOn: new \DateTimeImmutable()
        );
    }
}
