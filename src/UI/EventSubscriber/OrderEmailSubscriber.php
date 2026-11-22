<?php

declare(strict_types=1);

namespace App\UI\EventSubscriber;

use App\Application\Service\EmailNotificationService;
use App\Domain\Order\Event\OrderCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Слушатель событий заказов для отправки email уведомлений.
 */
final class OrderEmailSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EmailNotificationService $emailNotification
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            OrderCreatedEvent::class => 'onOrderCreated',
        ];
    }

    public function onOrderCreated(OrderCreatedEvent $event): void
    {
        // Отправляем подтверждение заказа покупателю
        if ($event->customerEmail !== null && $event->orderNumber !== null) {
            $this->emailNotification->sendOrderConfirmation(
                $event->customerEmail,
                $event->orderNumber,
                $event->totalAmount
            );
        }

        // TODO: Отправить уведомление продавцу (если есть разделение на продавцов)
    }
}
