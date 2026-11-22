<?php

declare(strict_types=1);

namespace App\Tests\Application\Handler;

use App\Application\Command\CreateProductCommand;
use App\Application\Handler\CreateProductHandler;
use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\Entity\Product;
use App\Domain\Shop\Repository\CategoryRepositoryInterface;
use App\Domain\Shop\ValueObject\CategoryId;
use App\Infrastructure\Storage\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Тесты для CreateProductHandler.
 *
 * @covers \App\Application\Handler\CreateProductHandler
 */
final class CreateProductHandlerTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private CategoryRepositoryInterface|MockObject $categoryRepository;
    private FileUploadService|MockObject $fileUploadService;
    private LoggerInterface|MockObject $logger;
    private CreateProductHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->fileUploadService = $this->createMock(FileUploadService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new CreateProductHandler(
            $this->entityManager,
            $this->categoryRepository,
            $this->fileUploadService,
            $this->logger
        );
    }

    public function testCreateProductWithoutImages(): void
    {
        // Arrange
        $categoryId = 1;
        $category = new Category('Electronics', 'electronics', 1);

        $command = new CreateProductCommand(
            title: 'Test Product',
            price: 99.99,
            description: 'Test Description',
            categoryId: $categoryId,
            available: 10
        );

        $this->categoryRepository
            ->expects($this->once())
            ->method('findById')
            ->with($this->equalTo(CategoryId::fromInt($categoryId)))
            ->willReturn($category);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Product::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->logger
            ->expects($this->atLeast(2))
            ->method('info');

        // Act
        $result = $this->handler($command);

        // Assert
        $this->assertInstanceOf(Product::class, $result);
        $this->assertEquals('Test Product', $result->getTitle());
        $this->assertEquals(99.99, $result->getPrice()->amountFloat());
        $this->assertEquals('Test Description', $result->getDescription());
        $this->assertEquals(10, $result->getAvailable());
    }

    public function testCreateProductWithImages(): void
    {
        // Arrange
        $categoryId = 1;
        $category = new Category('Electronics', 'electronics', 1);

        $uploadedFile = $this->createMock(UploadedFile::class);
        $uploadedFile->method('getClientOriginalName')->willReturn('test.jpg');
        $uploadedFile->method('getExtension')->willReturn('jpg');
        $uploadedFile->method('getSize')->willReturn(1024);

        $command = new CreateProductCommand(
            title: 'Test Product',
            price: 99.99,
            description: 'Test Description',
            categoryId: $categoryId,
            available: 10,
            images: [$uploadedFile]
        );

        $this->categoryRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($category);

        $this->fileUploadService
            ->expects($this->once())
            ->method('validate')
            ->with($uploadedFile);

        $this->fileUploadService
            ->expects($this->once())
            ->method('uploadProductImage')
            ->with($uploadedFile)
            ->willReturn('products/test_123.jpg');

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->logger
            ->expects($this->atLeast(3))
            ->method('info');

        // Act
        $result = $this->handler($command);

        // Assert
        $this->assertInstanceOf(Product::class, $result);
        $this->assertCount(1, $result->getImages());
    }

    public function testCreateProductWithInvalidCategory(): void
    {
        // Arrange
        $command = new CreateProductCommand(
            title: 'Test Product',
            price: 99.99,
            description: 'Test Description',
            categoryId: 999
        );

        $this->categoryRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Category not found');

        // Act
        $this->handler($command);
    }

    public function testCreateProductWithImageUploadError(): void
    {
        // Arrange
        $categoryId = 1;
        $category = new Category('Electronics', 'electronics', 1);

        $uploadedFile = $this->createMock(UploadedFile::class);
        $uploadedFile->method('getClientOriginalName')->willReturn('invalid.jpg');

        $command = new CreateProductCommand(
            title: 'Test Product',
            price: 99.99,
            description: 'Test Description',
            categoryId: $categoryId,
            images: [$uploadedFile]
        );

        $this->categoryRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($category);

        $this->fileUploadService
            ->expects($this->once())
            ->method('validate')
            ->willThrowException(new \RuntimeException('Invalid file'));

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('error')
            ->with(
                'Failed to upload image',
                $this->callback(static fn ($context) => isset($context['error'])
                    && $context['error'] === 'Invalid file'
                )
            );

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $result = $this->handler($command);

        // Assert - продукт создан, но без изображений
        $this->assertInstanceOf(Product::class, $result);
        $this->assertCount(0, $result->getImages());
    }

    public function testLoggerIsCalledWithCorrectContext(): void
    {
        // Arrange
        $categoryId = 1;
        $category = new Category('Electronics', 'electronics', 1);

        $command = new CreateProductCommand(
            title: 'Special Product',
            price: 150.00,
            description: 'Special Description',
            categoryId: $categoryId
        );

        $this->categoryRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($category);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

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
                $this->stringContains('Creating product'),
                $this->callback(static fn ($context) => isset($context['title'])
                    && $context['title'] === 'Special Product'
                )
            );
    }
}
