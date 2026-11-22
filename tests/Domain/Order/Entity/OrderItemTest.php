<?php

declare(strict_types=1);

namespace App\Tests\Domain\Order\Entity;

use App\Domain\Order\Entity\OrderItem;
use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\Entity\Product;
use PHPUnit\Framework\TestCase;

/**
 * Тесты для доменного объекта OrderItem.
 */
final class OrderItemTest extends TestCase
{
    public function testCreateOrderItem(): void
    {
        $product = new Product(
            'Test Product',
            '100.00',
            'Description',
            new Category('Test Category')
        );

        $orderItem = new OrderItem($product, 2);

        $this->assertSame($product, $orderItem->getProduct());
        $this->assertSame(2, $orderItem->getQuantity());
        $this->assertSame('100.00', $orderItem->getPrice());
        $this->assertSame(200.0, $orderItem->getTotal());
    }

    public function testUpdateQuantity(): void
    {
        $product = new Product(
            'Test Product',
            '100.00',
            'Description',
            new Category('Test Category')
        );

        $orderItem = new OrderItem($product, 2);
        $orderItem->setQuantity(3);

        $this->assertSame(3, $orderItem->getQuantity());
        $this->assertSame(300.0, $orderItem->getTotal());
    }

    public function testItemTotalCalculation(): void
    {
        $product = new Product(
            'Test Product',
            '99.99',
            'Description',
            new Category('Test Category')
        );

        $orderItem = new OrderItem($product, 3);

        // getTotal() performs float multiplication, so 99.99 * 3 is not exact.
        $this->assertEqualsWithDelta(299.97, $orderItem->getTotal(), 0.00001);
    }
}
