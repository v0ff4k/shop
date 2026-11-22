<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

use App\Domain\Shop\Entity\Product;
use App\Domain\Shop\Entity\ProductImage;

/**
 * DTO для ответа продукта.
 */
final class ProductResponse
{
    /**
     * @param array<int, ProductImageResponse> $images
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $slug,
        public readonly float $price,
        public readonly string $description,
        public readonly int $available,
        public readonly \DateTimeImmutable $added,
        public readonly int $viewed,
        public readonly ?CategoryResponse $category = null,
        public readonly array $images = [],
        public readonly ?\DateTimeImmutable $lastViewed = null
    ) {
    }

    public static function fromEntity(Product $product): self
    {
        $category = $product->getCategory();

        $images = array_map(
            static fn (ProductImage $img) => ProductImageResponse::fromEntity($img),
            $product->getImages()
        );

        return new self(
            $product->getId(),
            $product->getTitle(),
            $product->getSlug(),
            (float) $product->getPrice(),
            $product->getDescription(),
            $product->getAvailable(),
            $product->getAdded(),
            $product->getViewed(),
            $category ? CategoryResponse::fromEntity($category) : null,
            $images,
            $product->getLastViewed()
        );
    }
}
