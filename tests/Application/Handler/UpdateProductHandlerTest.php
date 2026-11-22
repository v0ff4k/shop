<?php

declare(strict_types=1);

namespace App\Tests\Application\Handler;

use App\Application\Command\UpdateProductCommand;
use App\Application\Handler\UpdateProductHandler;
use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\Entity\Product;
use App\Domain\Shop\Repository\ProductRepositoryInterface;
use App\Domain\Shop\ValueObject\ProductId;
use App\Infrastructure\Storage\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Тесты для UpdateProductHandler.
 *
 * @covers \App\Application\Handler\UpdateProductHandler
 */
final class UpdateProductHandlerTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private ProductRepositoryInterface|MockObject $productRepository;
    private FileUploadService|MockObject $fileUploadService;
    private LoggerInterface|MockObject $logger;
    private UpdateProductHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->fileUploadService = $this->createMock(FileUploadService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new UpdateProductHandler(
            $this->entityManager,
            $this->productRepository,
            $this->fileUploadService,
            $this->logger
        );
    }

    public function testUpdateProductTitle(): void
    {
        // Arrange
        $product = new Product('Old Title', 99.99, 'Description', $this->createCategory());

        $command = new UpdateProductCommand(
            productId: 42,
            title: 'New Title'
        );

        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->with($this->equalTo(ProductId::fromInt(42)))
            ->willReturn($product);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->logger
            ->expects($this->atLeast(2))
            ->method('info');

        // Act
        $this->handler($command);

        // Assert
        $this->assertEquals('New Title', $product->getTitle());
    }

    public function testUpdateProductPrice(): void
    {
        // Arrange
        $product = new Product('Title', 99.99, 'Description', $this->createCategory());

        $command = new UpdateProductCommand(
            productId: 42,
            price: 149.99
        );

        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($product);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $this->handler($command);

        // Assert
        $this->assertEquals(149.99, $product->getPrice()->amountFloat());
    }

    public function testUpdateProductDescription(): void
    {
        // Arrange
        $product = new Product('Title', 99.99, 'Old Description', $this->createCategory());

        $command = new UpdateProductCommand(
            productId: 42,
            description: 'New Description'
        );

        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($product);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $this->handler($command);

        // Assert
        $this->assertEquals('New Description', $product->getDescription());
    }

    public function testUpdateProductAvailable(): void
    {
        // Arrange
        $product = new Product('Title', 99.99, 'Description', $this->createCategory());
        $product->setAvailable(10);

        $command = new UpdateProductCommand(
            productId: 42,
            available: 50
        );

        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($product);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $this->handler($command);

        // Assert
        $this->assertEquals(50, $product->getAvailable());
    }

    public function testUpdateProductWithMultipleFields(): void
    {
        // Arrange
        $product = new Product('Old Title', 99.99, 'Old Description', $this->createCategory());

        $command = new UpdateProductCommand(
            productId: 42,
            title: 'New Title',
            price: 199.99,
            description: 'New Description',
            available: 100
        );

        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($product);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $this->handler($command);

        // Assert
        $this->assertEquals('New Title', $product->getTitle());
        $this->assertEquals(199.99, $product->getPrice()->amountFloat());
        $this->assertEquals('New Description', $product->getDescription());
        $this->assertEquals(100, $product->getAvailable());
    }

    public function testUpdateProductNotFound(): void
    {
        // Arrange
        $command = new UpdateProductCommand(
            productId: 999,
            title: 'New Title'
        );

        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product not found');

        // Act
        $this->handler($command);
    }

    public function testUpdateProductWithNullValues(): void
    {
        // Arrange
        $product = new Product('Title', 99.99, 'Description', $this->createCategory());

        $command = new UpdateProductCommand(
            productId: 42,
            title: null,
            price: null,
            description: null,
            available: null
        );

        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($product);

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        // Act
        $this->handler($command);

        // Assert - ничего не изменилось
        $this->assertEquals('Title', $product->getTitle());
        $this->assertEquals(99.99, $product->getPrice()->amountFloat());
    }

    public function testLoggerContext(): void
    {
        // Arrange
        $product = new Product('Title', 99.99, 'Description', $this->createCategory());

        $command = new UpdateProductCommand(
            productId: 42,
            title: 'New Title'
        );

        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($product);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $this->handler($command);

        // Assert
        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info')
            ->with(
                $this->stringContains('Updating product'),
                $this->callback(static fn ($context) => isset($context['id'])
                )
            );
    }

    private function createCategory(): Category
    {
        return new Category('Test Category', 'test-category', 1);
    }
}
