<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

use App\UI\Request\ProductSearchRequestDto;

/**
 * DTO для запроса поиска продуктов.
 */
final class ProductSearchRequest
{
    public function __construct(
        public readonly ?string $query = null,
        public readonly ?int $categoryId = null,
        public readonly ?float $minPrice = null,
        public readonly ?float $maxPrice = null,
        public readonly int $page = 1,
        public readonly int $limit = 20,
        public readonly string $sortBy = 'added',
        public readonly string $sortOrder = 'desc'
    ) {
    }

    /**
     * Создание из UI DTO.
     */
    public static function fromUiDto(ProductSearchRequestDto $dto): self
    {
        return new self(
            query: $dto->query,
            categoryId: $dto->categoryId,
            minPrice: $dto->minPrice,
            maxPrice: $dto->maxPrice,
            page: $dto->page,
            limit: $dto->limit,
            sortBy: $dto->sortBy,
            sortOrder: $dto->sortOrder
        );
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    public function getValidSortBy(): string
    {
        $allowed = ['added', 'price', 'title', 'viewed'];

        return \in_array($this->sortBy, $allowed, true) ? $this->sortBy : 'added';
    }

    public function getValidSortOrder(): string
    {
        return strtolower($this->sortOrder) === 'asc' ? 'asc' : 'desc';
    }
}
