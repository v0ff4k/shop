<?php

declare(strict_types=1);

namespace Tests\Domain\Shop\Event;

use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\Entity\Product;
use App\Domain\Shop\Event\ProductViewedEvent;
use PHPUnit\Framework\TestCase;

final class ProductViewedEventTest extends TestCase
{
    public function testEventCreation(): void
    {
        $product = new Product('Test Product', '100.00', 'Description', new Category('Test Category', 'test-category'));
        $product->incrementViewed();

        $property = new \ReflectionProperty($product, 'id');
        $property->setAccessible(true);
        $property->setValue($product, 42);

        $event = ProductViewedEvent::fromProduct($product);

        self::assertSame(42, $event->productId);
        self::assertSame('test-product', $event->slug);
        self::assertSame(1, $event->viewedCount);
        self::assertInstanceOf(\DateTimeImmutable::class, $event->lastViewed);
        self::assertInstanceOf(\DateTimeImmutable::class, $event->occurredOn);
    }
}
