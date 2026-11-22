<?php

declare(strict_types=1);

namespace App\UI\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO для запроса подсказок поиска продуктов.
 */
final class ProductSuggestRequestDto
{
    #[Assert\NotBlank(message: 'Search query is required')]
    #[Assert\Length(min: 2, max: 255)]
    #[Assert\Type(type: 'string')]
    public readonly string $query;

    #[Assert\Positive]
    #[Assert\Range(min: 1, max: 10)]
    public readonly int $limit;

    private function __construct(string $query, int $limit)
    {
        $this->query = $query;
        $this->limit = $limit;
    }

    public static function fromRequest(Request $request): self
    {
        $query = trim((string) $request->query->get('q', ''));
        $limit = max(1, min(10, $request->query->getInt('limit', 8)));

        return new self($query, $limit);
    }
}
