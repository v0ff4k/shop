<?php

declare(strict_types=1);

namespace Tests\Domain\Order\Event;

use App\Domain\Order\Entity\Order;
use App\Domain\Order\Event\OrderCreatedEvent;
use PHPUnit\Framework\TestCase;

final class OrderCreatedEventTest extends TestCase
{
    public function testEventCreation(): void
    {
        $order = new Order('John Doe', 'Some delivery address');
        $order->setTotalAmount(123.45);

        $property = new \ReflectionProperty($order, 'id');
        $property->setAccessible(true);
        $property->setValue($order, 55);

        $event = OrderCreatedEvent::fromOrder($order);

        self::assertSame(55, $event->orderId);
        self::assertNotNull($event->orderNumber);
        self::assertStringStartsWith('ORD-', $event->orderNumber);
        self::assertNull($event->userId);
        self::assertNull($event->customerEmail);
        self::assertSame('John Doe', $event->customerName);
        self::assertSame(123.45, $event->totalAmount);
        self::assertSame([], $event->items);
        self::assertInstanceOf(\DateTimeImmutable::class, $event->occurredOn);
    }
}
