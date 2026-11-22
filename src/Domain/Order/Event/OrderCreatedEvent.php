<?php

declare(strict_types=1);

namespace App\Domain\Order\Event;

use App\Domain\Order\Entity\Order;

/**
 * Событие: заказ создан.
 */
final class OrderCreatedEvent
{
    /**
     * @param array<array{product_id: int|null, quantity: int, price: float}> $items
     */
    public function __construct(
        public readonly ?int $orderId,
        public readonly ?string $orderNumber,
        public readonly ?int $userId,
        public readonly ?string $customerEmail,
        public readonly string $customerName,
        public readonly float $totalAmount,
        public readonly array $items,
        public readonly \DateTimeImmutable $occurredOn,
    ) {
    }

    public static function fromOrder(Order $order): self
    {
        $items = array_map(
            static fn ($item) => [
                'product_id' => $item->getProduct()?->getId(),
                'quantity' => $item->getQuantity(),
                'price' => (float) $item->getPrice(),
            ],
            $order->getItems()
        );

        return new self(
            orderId: $order->getId(),
            orderNumber: $order->getOrderNumber(),
            userId: $order->getUser()?->getId(),
            customerEmail: $order->getUser()?->getEmail(),
            customerName: $order->getCustomerName(),
            totalAmount: $order->getTotalAmount(),
            items: $items,
            occurredOn: new \DateTimeImmutable()
        );
    }
}
