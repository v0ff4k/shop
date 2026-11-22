<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

use App\Domain\Shop\Entity\Category;

/**
 * DTO для ответа категории.
 */
final class CategoryResponse
{
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $slug,
        public readonly ?string $image,
        public readonly \DateTimeImmutable $added,
        public readonly ?\DateTimeImmutable $edited = null,
        public readonly ?int $parentId = null,
        /** @var array<int, CategoryResponse> */
        public readonly array $children = [],
        public readonly int $productsCount = 0
    ) {
    }

    public static function fromEntity(
        Category $category,
        int $productsCount = 0,
        bool $withChildren = false
    ): self {
        $parent = $category->getParent();
        $children = [];

        if ($withChildren) {
            $children = array_map(
                static fn (Category $child) => self::fromEntity($child, 0, true),
                $category->getChildren()->toArray()
            );
        }

        return new self(
            $category->getId(),
            $category->getTitle(),
            $category->getSlug(),
            $category->getImage(),
            $category->getAdded(),
            $category->getEdited(),
            $parent?->getId(),
            $children,
            $productsCount
        );
    }
}
