<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * Команда для создания заказа.
 */
final class CreateOrderCommand implements IdempotentCommandInterface
{
    use IdempotentCommandTrait;

    /**
     * @param array<array{product_id: int, quantity: int}> $items
     */
    public function __construct(
        public readonly string $customerName,
        public readonly string $deliveryAddress,
        public readonly array $items,
        public readonly ?string $additionalInfo = null,
        public readonly ?int $userId = null,
        ?string $idempotencyKey = null
    ) {
        $this->idempotencyKey = $idempotencyKey;
    }
}
