<?php

declare(strict_types=1);

namespace App\UI\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO для GET /api/products.
 */
final class ProductListRequestDto
{
    #[Assert\Positive]
    #[Assert\Range(min: 1, max: 100)]
    public readonly int $limit;

    #[Assert\PositiveOrZero]
    #[Assert\Range(min: 0, max: 1000000)]
    public readonly int $offset;

    #[Assert\Positive]
    public readonly ?int $categoryId;

    #[Assert\Length(max: 255)]
    public readonly ?string $search;

    private function __construct(int $limit, int $offset, ?int $categoryId, ?string $search)
    {
        $this->limit = $limit;
        $this->offset = $offset;
        $this->categoryId = $categoryId;
        $this->search = $search;
    }

    public static function fromRequest(Request $request): self
    {
        $category = $request->query->get('category');
        $search = trim((string) $request->query->get('search', ''));

        return new self(
            limit: max(1, min(100, $request->query->getInt('limit', 20))),
            offset: max(0, $request->query->getInt('offset', 0)),
            categoryId: $category === null || $category === '' ? null : max(1, (int) $category),
            search: $search === '' ? null : $search
        );
    }

    public function hasSearch(): bool
    {
        return $this->search !== null;
    }

    public function hasCategory(): bool
    {
        return $this->categoryId !== null;
    }
}
