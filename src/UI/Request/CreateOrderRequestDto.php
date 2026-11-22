<?php

declare(strict_types=1);

namespace App\UI\Request;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO для запроса создания заказа.
 */
final class CreateOrderRequestDto extends IdempotentRequestDto
{
    #[Assert\NotBlank(message: 'Customer name is required')]
    #[Assert\Length(min: 2, max: 255, minMessage: 'Name must be at least {{ limit }} characters', maxMessage: 'Name cannot exceed {{ limit }} characters')]
    #[Assert\Type(type: 'string')]
    public readonly string $customerName;

    #[Assert\NotBlank(message: 'Delivery address is required')]
    #[Assert\Length(min: 10, max: 1000, minMessage: 'Address must be at least {{ limit }} characters')]
    #[Assert\Type(type: 'string')]
    public readonly string $deliveryAddress;

    #[Assert\Length(max: 1000)]
    #[Assert\Type(type: 'string')]
    public readonly ?string $additionalInfo;

    /**
     * @var array<array{product_id: int, quantity: int}>
     */
    #[Assert\NotBlank(message: 'Order must contain at least one item')]
    #[Assert\Type(type: 'array')]
    #[Assert\All([
        new Assert\Type(type: 'array'),
        new Assert\Collection([
            'product_id' => [
                new Assert\NotBlank(),
                new Assert\Positive(),
                new Assert\Type(type: 'integer'),
            ],
            'quantity' => [
                new Assert\NotBlank(),
                new Assert\Positive(),
                new Assert\Type(type: 'integer'),
            ],
        ]),
    ])]
    public readonly array $items;

    /**
     * @param array<array{product_id: int, quantity: int}> $items
     */
    private function __construct(
        string $customerName,
        string $deliveryAddress,
        ?string $additionalInfo,
        array $items,
        ?string $idempotencyKey = null
    ) {
        $this->customerName = $customerName;
        $this->deliveryAddress = $deliveryAddress;
        $this->additionalInfo = $additionalInfo;
        $this->items = $items;
        $this->setIdempotencyKey($idempotencyKey);
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            customerName: (string) $request->request->get('customer_name', ''),
            deliveryAddress: (string) $request->request->get('delivery_address', ''),
            additionalInfo: $request->request->get('additional_info'),
            items: $request->request->all('items') ?: [],
            idempotencyKey: $request->headers->get('X-Idempotency-Key')
        );
    }

    public static function fromJson(array $data): self
    {
        return new self(
            customerName: (string) ($data['customer_name'] ?? ''),
            deliveryAddress: (string) ($data['delivery_address'] ?? ''),
            additionalInfo: $data['additional_info'] ?? null,
            items: $data['items'] ?? [],
            idempotencyKey: $data['idempotency_key'] ?? null
        );
    }
}
