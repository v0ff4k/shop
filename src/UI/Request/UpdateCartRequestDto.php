<?php

declare(strict_types=1);

namespace App\UI\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO для запроса обновления количества товара в корзине.
 */
final class UpdateCartRequestDto
{
    #[Assert\NotBlank(message: 'Product ID is required')]
    #[Assert\Positive(message: 'Product ID must be positive')]
    public readonly int $productId;

    #[Assert\Positive(message: 'Quantity must be at least 1')]
    #[Assert\Range(min: 1, max: 999)]
    public readonly int $quantity;

    private function __construct(int $productId, int $quantity)
    {
        $this->productId = $productId;
        $this->quantity = $quantity;
    }

    public static function fromRequest(Request $request): self
    {
        $productId = (int) $request->attributes->get('productId', 0);
        $quantity = (int) $request->request->get('quantity', 1);

        return new self(
            productId: $productId,
            quantity: max(1, $quantity)
        );
    }
}
