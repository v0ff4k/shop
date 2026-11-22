<?php

declare(strict_types=1);

namespace App\Application\DTO\Response;

use App\Domain\Order\Entity\Order;
use App\Domain\Order\Entity\OrderItem;

/**
 * DTO для ответа заказа.
 */
final class OrderResponse
{
    /**
     * @param array<int, OrderItemResponse> $items
     */
    public function __construct(
        public readonly int $id,
        public readonly string $orderNumber,
        public readonly ?int $userId,
        public readonly string $customerName,
        public readonly string $deliveryAddress,
        public readonly ?string $additionalInfo,
        public readonly float $totalAmount,
        public readonly string $status,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?\DateTimeImmutable $updatedAt = null,
        public readonly ?\DateTimeImmutable $paidAt = null,
        public readonly ?\DateTimeImmutable $shippedAt = null,
        public readonly array $items = []
    ) {
    }

    public static function fromEntity(Order $order): self
    {
        $user = $order->getUser();
        $items = array_map(
            static fn (OrderItem $item) => OrderItemResponse::fromEntity($item),
            $order->getItems()
        );

        return new self(
            $order->getId(),
            $order->getOrderNumber(),
            $user?->getId(),
            $order->getCustomerName(),
            $order->getDeliveryAddress(),
            $order->getAdditionalInfo(),
            $order->getTotalAmount(),
            $order->getStatus(),
            $order->getCreatedAt(),
            $order->getUpdatedAt(),
            $order->getPaidAt(),
            $order->getShippedAt(),
            $items
        );
    }
}
