<?php

declare(strict_types=1);

namespace App\Application\DTO\Request;

/**
 * DTO для запроса создания заказа.
 */
final class CreateOrderRequest
{
    public function __construct(
        public readonly string $customerName,
        public readonly string $deliveryAddress,
        public readonly ?string $additionalInfo = null,
        public readonly array $items = []
    ) {
    }
}
