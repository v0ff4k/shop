<?php

declare(strict_types=1);

namespace App\Tests\Application\Handler;

use App\Application\Command\DeleteProductCommand;
use App\Application\Handler\DeleteProductHandler;
use App\Domain\Shop\Entity\Product;
use App\Domain\Shop\Entity\ProductImage;
use App\Domain\Shop\Repository\ProductRepositoryInterface;
use App\Domain\Shop\ValueObject\ProductId;
use App\Infrastructure\Storage\FileUploadService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Тесты для DeleteProductHandler.
 *
 * @covers \App\Application\Handler\DeleteProductHandler
 */
final class DeleteProductHandlerTest extends TestCase
{
    private ProductRepositoryInterface|MockObject $productRepository;
    private FileUploadService|MockObject $fileUploadService;
    private LoggerInterface|MockObject $logger;
    private DeleteProductHandler $handler;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->fileUploadService = $this->createMock(FileUploadService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new DeleteProductHandler(
            $this->productRepository,
            $this->fileUploadService,
            $this->logger
        );
    }

    public function testDeleteProduct(): void
    {
        // Arrange
        $productId = 42;
        $product = new Product('Test Product', 99.99, 'Description', $this->createCategory());

        $command = new DeleteProductCommand($productId);

        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->with($this->equalTo(ProductId::fromInt($productId)))
            ->willReturn($product);

        $this->productRepository
            ->expects($this->once())
            ->method('remove')
            ->with($this->equalTo($product));

        $this->logger
            ->expects($this->atLeast(2))
            ->method('info');

        // Act
        $this->handler($command);
    }

    public function testDeleteProductWithImages(): void
    {
        // Arrange
        $productId = 42;
        $product = new Product('Test Product', 99.99, 'Description', $this->createCategory());

        // Добавляем изображения к продукту
        $image1 = new ProductImage('image1.jpg');
        $image2 = new ProductImage('image2.jpg');
        $product->addImage($image1);
        $product->addImage($image2);

        $command = new DeleteProductCommand($productId);

        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($product);

        $this->fileUploadService
            ->expects($this->exactly(2))
            ->method('deleteFile')
            ->withConsecutive(
                ['image1.jpg', 'products'],
                ['image2.jpg', 'products']
            );

        $this->productRepository
            ->expects($this->once())
            ->method('remove');

        $this->logger
            ->expects($this->atLeast(4))
            ->method('info');

        // Act
        $this->handler($command);
    }

    public function testDeleteProductNotFound(): void
    {
        // Arrange
        $command = new DeleteProductCommand(999);

        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product not found');

        // Act
        $this->handler($command);
    }

    public function testDeleteProductWithImageDeleteError(): void
    {
        // Arrange
        $productId = 42;
        $product = new Product('Test Product', 99.99, 'Description', $this->createCategory());
        $image = new ProductImage('image.jpg');
        $product->addImage($image);

        $command = new DeleteProductCommand($productId);

        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($product);

        $this->fileUploadService
            ->expects($this->once())
            ->method('deleteFile')
            ->willThrowException(new \RuntimeException('File not found'));

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('error')
            ->with(
                'Failed to delete image',
                $this->callback(static fn ($context) => isset($context['error'])
                    && $context['error'] === 'File not found'
                )
            );

        $this->productRepository
            ->expects($this->once())
            ->method('remove');

        // Act
        $this->handler($command);

        // Assert - продукт удалён, несмотря на ошибку удаления изображения
        $this->assertTrue(true);
    }

    public function testLoggerContext(): void
    {
        // Arrange
        $productId = 42;
        $product = new Product('Test Product', 99.99, 'Description', $this->createCategory());
        $command = new DeleteProductCommand($productId);

        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($product);

        $this->productRepository
            ->expects($this->once())
            ->method('remove');

        // Act
        $this->handler($command);

        // Assert
        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info')
            ->with(
                $this->stringContains('Deleting product'),
                $this->callback(static fn ($context) => isset($context['id'])
                )
            );
    }

    private function createCategory(): \App\Domain\Shop\Entity\Category
    {
        return new \App\Domain\Shop\Entity\Category('Test Category', 'test-category', 1);
    }
}
