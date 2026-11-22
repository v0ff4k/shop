<?php

declare(strict_types=1);

namespace App\UI\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO для GET /api/product/{id}.
 */
final class ProductShowRequestDto
{
    #[Assert\Positive]
    public readonly int $id;

    private function __construct(int $id)
    {
        $this->id = $id;
    }

    public static function fromRequest(Request $request): self
    {
        return new self((int) $request->attributes->get('id'));
    }
}
