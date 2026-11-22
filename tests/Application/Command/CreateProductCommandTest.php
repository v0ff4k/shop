<?php

declare(strict_types=1);

namespace App\Tests\Application\Command;

use App\Application\Command\CreateProductCommand;
use PHPUnit\Framework\TestCase;

final class CreateProductCommandTest extends TestCase
{
    public function testCreateProductCommand(): void
    {
        $command = new CreateProductCommand(
            title: 'Test Product',
            description: 'Test Description',
            price: 99.99,
            categoryId: 5,
            available: 10,
            slug: 'test-product'
        );

        $this->assertEquals('Test Product', $command->title);
        $this->assertEquals('Test Description', $command->description);
        $this->assertEquals(99.99, $command->price);
        $this->assertEquals(5, $command->categoryId);
        $this->assertEquals(10, $command->available);
        $this->assertEquals('test-product', $command->slug);
    }

    public function testCreateProductCommandWithOptionalFields(): void
    {
        $command = new CreateProductCommand(
            title: 'Test Product',
            description: null,
            price: 149.99,
            categoryId: 3,
            available: 0,
            slug: null
        );

        $this->assertNull($command->description);
        $this->assertNull($command->slug);
        $this->assertEquals(0, $command->available);
    }
}
