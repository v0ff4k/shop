<?php

declare(strict_types=1);

namespace Tests\Domain\Shop\Event;

use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\Entity\Product;
use App\Domain\Shop\Event\ProductCreatedEvent;
use PHPUnit\Framework\TestCase;

final class ProductCreatedEventTest extends TestCase
{
    public function testEventCreation(): void
    {
        $category = new Category('Test Category', 'test-category');
        $product = new Product('Test Product', '100.00', 'Description', $category);

        $this->setId($category, 7);
        $this->setId($product, 42);

        $event = ProductCreatedEvent::fromProduct($product);

        self::assertSame(42, $event->productId);
        self::assertSame('Test Product', $event->title);
        self::assertSame('test-product', $event->slug);
        self::assertSame(100.0, $event->price);
        self::assertSame(7, $event->categoryId);
        self::assertInstanceOf(\DateTimeImmutable::class, $event->occurredOn);
    }

    private function setId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
