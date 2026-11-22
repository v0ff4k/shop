<?php

declare(strict_types=1);

namespace App\UI\EventSubscriber;

use App\Domain\Order\Event\OrderCreatedEvent;
use App\Domain\Shop\Event\ProductCreatedEvent;
use App\Domain\Shop\Event\ProductViewedEvent;
use App\Domain\User\Event\UserRegisteredEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Слушатель доменных событий для логирования.
 */
#[AsEventListener(event: ProductCreatedEvent::class, method: 'onProductCreated')]
#[AsEventListener(event: ProductViewedEvent::class, method: 'onProductViewed')]
#[AsEventListener(event: OrderCreatedEvent::class, method: 'onOrderCreated')]
#[AsEventListener(event: UserRegisteredEvent::class, method: 'onUserRegistered')]
final class DomainEventLoggerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [];
    }

    public function onProductCreated(ProductCreatedEvent $event): void
    {
        $this->logger->info(
            'Product created',
            [
                'product_id' => $event->productId,
                'title' => $event->title,
                'slug' => $event->slug,
                'price' => $event->price,
                'category_id' => $event->categoryId,
            ]
        );
    }

    public function onProductViewed(ProductViewedEvent $event): void
    {
        $this->logger->info(
            'Product viewed',
            [
                'product_id' => $event->productId,
                'slug' => $event->slug,
                'viewed_count' => $event->viewedCount,
            ]
        );
    }

    public function onOrderCreated(OrderCreatedEvent $event): void
    {
        $this->logger->info(
            'Order created',
            [
                'order_id' => $event->orderId,
                'order_number' => $event->orderNumber,
                'user_id' => $event->userId,
                'customer_name' => $event->customerName,
                'total_amount' => $event->totalAmount,
                'items_count' => \count($event->items),
            ]
        );
    }

    public function onUserRegistered(UserRegisteredEvent $event): void
    {
        $this->logger->info(
            'User registered',
            [
                'user_id' => $event->userId,
                'username' => $event->username,
                'email' => $event->email,
                'roles' => $event->roles,
            ]
        );
    }
}
