<?php

declare(strict_types=1);

namespace App\Domain\Shop\Event;

use App\Domain\Shop\Entity\Product;

/**
 * Событие: продукт просмотрен.
 */
final class ProductViewedEvent
{
    public function __construct(
        public readonly ?int $productId,
        public readonly string $slug,
        public readonly int $viewedCount,
        public readonly ?\DateTimeImmutable $lastViewed,
        public readonly \DateTimeImmutable $occurredOn,
    ) {
    }

    public static function fromProduct(Product $product): self
    {
        return new self(
            productId: $product->getId(),
            slug: $product->getSlug(),
            viewedCount: $product->getViewed(),
            lastViewed: $product->getLastViewed(),
            occurredOn: new \DateTimeImmutable()
        );
    }
}
