<?php

declare(strict_types=1);

namespace App\Domain\Order\Security;

use App\Domain\Order\Repository\OrderRepositoryInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class OrderAccessVoter
{
    public function __construct(
        private OrderRepositoryInterface $orderRepository
    ) {
    }

    /**
     * Проверяет, принадлежит ли заказ из URL текущему пользователю.
     */
    public function isOrderOwner(int $orderId, ?UserInterface $user): bool
    {
        if (!$user) {
            return false;
        }

        $order = $this->orderRepository->findById($orderId);
        if (!$order) {
            // Если заказ не найден, возвращаем true или false
            // (лучше false, тогда Symfony выдаст 403 вместо 404, защищая от перебора ID)
            return false;
        }

        // Чистая бизнес-логика сравнения идентификаторов (DDD-style)
        return $order->getUserId() === $user->getId();
    }
}
