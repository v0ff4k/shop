<?php

declare(strict_types=1);

namespace App\UI\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO для запроса удаления из корзины.
 */
final class RemoveFromCartRequestDto
{
    #[Assert\NotBlank(message: 'Product ID is required')]
    #[Assert\Positive(message: 'Product ID must be positive')]
    #[Assert\Type(type: 'integer', message: 'Product ID must be an integer')]
    public readonly int $productId;

    private function __construct(int $productId)
    {
        $this->productId = $productId;
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            productId: (int) $request->attributes->get('productId', 0)
        );
    }

    public static function fromInt(int $productId): self
    {
        return new self($productId);
    }
}
