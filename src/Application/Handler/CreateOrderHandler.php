<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\CreateOrderCommand;
use App\Domain\Order\Entity\Order;
use App\Domain\Order\Entity\OrderItem;
use App\Domain\Shop\Repository\ProductRepositoryInterface;
use App\Domain\Shop\ValueObject\ProductId;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Обработчик команды создания заказа.
 */
#[AsMessageHandler]
final class CreateOrderHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly UserRepositoryInterface $userRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(CreateOrderCommand $command): Order
    {
        $startedAt = microtime(true);
        $this->logger->info(
            'Creating order',
            [
                'customer' => $command->customerName,
                'items_count' => \count($command->items),
            ]
        );

        // Создаем заказ
        $user = null;
        if ($command->userId !== null) {
            $user = $this->userRepository->findById(UserId::fromInt($command->userId));
        }

        $order = new Order(
            $command->customerName,
            $command->deliveryAddress,
            $user
        );

        if ($command->additionalInfo !== null) {
            $order->setAdditionalInfo($command->additionalInfo);
        }

        // Добавляем элементы заказа
        foreach ($command->items as $itemData) {
            $product = $this->productRepository->findById(ProductId::fromInt($itemData['product_id']));

            if ($product === null) {
                throw new \InvalidArgumentException(\sprintf('Product with ID %d not found', $itemData['product_id']));
            }

            if (!$product->isAvailable() || $product->getAvailable() < $itemData['quantity']) {
                throw new \InvalidArgumentException(\sprintf('Product "%s" is not available in requested quantity', $product->getTitle()));
            }

            $orderItem = new OrderItem($product, $itemData['quantity']);
            $order->addItem($orderItem);

            // Уменьшаем количество доступного товара
            $product->setAvailable($product->getAvailable() - $itemData['quantity']);
        }

        // Сохраняем заказ
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        $this->logger->info(
            'Order created',
            [
                'order_number' => $order->getOrderNumber(),
                'total' => $order->getTotalAmount(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]
        );

        return $order;
    }
}
