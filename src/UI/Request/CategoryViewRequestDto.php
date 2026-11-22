<?php

declare(strict_types=1);

namespace App\UI\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO для запроса просмотра категории.
 */
final class CategoryViewRequestDto
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 128)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Slug must be a valid URL-friendly string')]
    public readonly string $slug;

    #[Assert\Positive]
    #[Assert\Range(min: 1, max: 10000)]
    public readonly int $page;

    #[Assert\Positive]
    #[Assert\Range(min: 1, max: 100)]
    public readonly int $limit;

    private function __construct(string $slug, int $page, int $limit)
    {
        $this->slug = $slug;
        $this->page = $page;
        $this->limit = $limit;
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            slug: $request->attributes->get('slug'),
            page: max(1, $request->query->getInt('page', 1)),
            limit: min(100, max(1, $request->query->getInt('limit', 20)))
        );
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->limit;
    }
}
