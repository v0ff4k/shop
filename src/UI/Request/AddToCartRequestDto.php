<?php

declare(strict_types=1);

namespace App\UI\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO для запроса добавления в корзину.
 */
final class AddToCartRequestDto
{
    #[Assert\NotBlank(message: 'Product ID is required')]
    #[Assert\Positive(message: 'Product ID must be positive')]
    #[Assert\Type(type: 'integer', message: 'Product ID must be an integer')]
    public readonly int $productId;

    #[Assert\Positive(message: 'Quantity must be at least 1')]
    #[Assert\Range(min: 1, max: 999, notInRangeMessage: 'Quantity must be between {{ min }} and {{ max }}')]
    #[Assert\Type(type: 'integer', message: 'Quantity must be an integer')]
    public readonly int $quantity;

    private function __construct(int $productId, int $quantity)
    {
        $this->productId = $productId;
        $this->quantity = $quantity;
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            productId: (int) $request->request->get('product_id', 0),
            quantity: max(1, (int) $request->request->get('quantity', 1))
        );
    }

    public static function fromJson(array $data): self
    {
        return new self(
            productId: (int) ($data['product_id'] ?? 0),
            quantity: max(1, (int) ($data['quantity'] ?? 1))
        );
    }
}
