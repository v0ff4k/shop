<?php

declare(strict_types=1);

namespace App\UI\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO для запроса просмотра продукта.
 */
final class ProductViewRequestDto
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 128)]
    #[Assert\Regex(pattern: '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', message: 'Slug must be a valid URL-friendly string')]
    public readonly string $slug;

    private function __construct(string $slug)
    {
        $this->slug = $slug;
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            slug: $request->attributes->get('slug')
        );
    }
}
