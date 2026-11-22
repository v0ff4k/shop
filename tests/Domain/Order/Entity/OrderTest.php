<?php

declare(strict_types=1);

namespace App\Tests\Domain\Order\Entity;

use App\Domain\Order\Entity\Order;
use App\Domain\Order\Entity\OrderItem;
use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\Entity\Product;
use PHPUnit\Framework\TestCase;

/**
 * Тесты для доменной сущности Order.
 */
final class OrderTest extends TestCase
{
    public function testCreateOrder(): void
    {
        $order = new Order('John Doe', 'Some delivery address');

        $this->assertNull($order->getUser());
        $this->assertSame('John Doe', $order->getCustomerName());
        $this->assertSame('Some delivery address', $order->getDeliveryAddress());
        $this->assertSame(Order::STATUS_NEW, $order->getStatus());
        $this->assertNotNull($order->getOrderNumber());
        $this->assertNotNull($order->getCreatedAt());
        $this->assertNull($order->getPaidAt());
        $this->assertNull($order->getShippedAt());
        $this->assertNull($order->getUpdatedAt());
        $this->assertSame(0.0, $order->getTotalAmount());
        $this->assertCount(0, $order->getItems());
    }

    public function testAddItemToOrder(): void
    {
        $order = new Order('John Doe', 'Some delivery address');

        $product = new Product(
            'Test Product',
            '100.00',
            'Description',
            new Category('Test Category')
        );

        $orderItem = new OrderItem($product, 2);
        $order->addItem($orderItem);

        $this->assertCount(1, $order->getItems());
        $this->assertSame($order, $orderItem->getOrder());
        $this->assertSame(200.0, $order->getTotalAmount());
    }

    public function testUpdateOrderStatus(): void
    {
        $order = new Order('John Doe', 'Some delivery address');

        $order->markAsPaid();
        $this->assertSame('paid', $order->getStatus());
        $this->assertNotNull($order->getPaidAt());

        $order->markAsShipped();
        $this->assertSame(Order::STATUS_SHIPPED, $order->getStatus());
        $this->assertNotNull($order->getShippedAt());
    }

    public function testOrderCanBeCancelled(): void
    {
        $order = new Order('John Doe', 'Some delivery address');

        $order->setStatus(Order::STATUS_CANCELLED);

        $this->assertSame(Order::STATUS_CANCELLED, $order->getStatus());
    }

    public function testOrderTotalAmountCalculation(): void
    {
        $order = new Order('John Doe', 'Some delivery address');
        $category = new Category('Test Category');

        $product1 = new Product(
            'Product 1',
            '100.00',
            'Description',
            $category
        );

        $product2 = new Product(
            'Product 2',
            '50.00',
            'Description',
            $category
        );

        $order->addItem(new OrderItem($product1, 2));
        $order->addItem(new OrderItem($product2, 1));

        $this->assertSame(250.0, $order->getTotalAmount());
    }
}
