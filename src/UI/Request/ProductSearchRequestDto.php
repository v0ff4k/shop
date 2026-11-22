<?php

declare(strict_types=1);

namespace App\UI\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO для запроса поиска продуктов.
 */
final class ProductSearchRequestDto
{
    #[Assert\Length(max: 255, groups: ['search'])]
    #[Assert\Type(type: 'string', groups: ['search'])]
    public readonly ?string $query;

    #[Assert\Positive(groups: ['filter'])]
    #[Assert\Type(type: 'integer', groups: ['filter'])]
    public readonly ?int $categoryId;

    #[Assert\Positive(groups: ['filter'])]
    #[Assert\Type(type: 'numeric', groups: ['filter'])]
    public readonly ?float $minPrice;

    #[Assert\Positive(groups: ['filter'])]
    #[Assert\Type(type: 'numeric', groups: ['filter'])]
    public readonly ?float $maxPrice;

    #[Assert\Positive]
    #[Assert\Range(min: 1, max: 10000)]
    public readonly int $page;

    #[Assert\Positive]
    #[Assert\Range(min: 1, max: 100)]
    public readonly int $limit;

    #[Assert\Choice(choices: ['added', 'price', 'title', 'viewed'])]
    public readonly string $sortBy;

    #[Assert\Choice(choices: ['asc', 'desc'])]
    public readonly string $sortOrder;

    private function __construct(
        ?string $query,
        ?int $categoryId,
        ?float $minPrice,
        ?float $maxPrice,
        int $page,
        int $limit,
        string $sortBy,
        string $sortOrder
    ) {
        $this->query = $query;
        $this->categoryId = $categoryId;
        $this->minPrice = $minPrice;
        $this->maxPrice = $maxPrice;
        $this->page = $page;
        $this->limit = $limit;
        $this->sortBy = $sortBy;
        $this->sortOrder = $sortOrder;
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            query: self::nullableString($request->query->get('q')),
            categoryId: self::nullableInt($request->query->get('category_id')),
            minPrice: self::nullableFloat($request->query->get('min_price')),
            maxPrice: self::nullableFloat($request->query->get('max_price')),
            page: max(1, $request->query->getInt('page', 1)),
            limit: min(100, max(1, $request->query->getInt('limit', 20))),
            sortBy: $request->query->get('sort_by', 'added'),
            sortOrder: $request->query->get('sort_order', 'desc')
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function nullableInt(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        $value = (int) $value;

        return $value <= 0 ? null : $value;
    }

    private static function nullableFloat(mixed $value): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return (float) $value;
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    public function hasQuery(): bool
    {
        return $this->query !== null && trim($this->query) !== '';
    }

    public function hasCategoryFilter(): bool
    {
        return $this->categoryId !== null;
    }

    public function hasPriceFilter(): bool
    {
        return $this->minPrice !== null || $this->maxPrice !== null;
    }
}
