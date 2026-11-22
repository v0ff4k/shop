<?php

declare(strict_types=1);

namespace App\Tests\Application\Handler;

use App\Application\Command\CreateOrderCommand;
use App\Application\Handler\CreateOrderHandler;
use App\Domain\Order\Entity\Order;
use App\Domain\Shop\Entity\Product;
use App\Domain\Shop\Repository\ProductRepositoryInterface;
use App\Domain\Shop\ValueObject\ProductId;
use App\Domain\User\Entity\User;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Domain\User\ValueObject\UserId;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Тесты для CreateOrderHandler.
 *
 * @covers \App\Application\Handler\CreateOrderHandler
 */
final class CreateOrderHandlerTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private ProductRepositoryInterface|MockObject $productRepository;
    private UserRepositoryInterface|MockObject $userRepository;
    private LoggerInterface|MockObject $logger;
    private CreateOrderHandler $handler;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->handler = new CreateOrderHandler(
            $this->entityManager,
            $this->productRepository,
            $this->userRepository,
            $this->logger
        );
    }

    public function testCreateOrderWithoutUser(): void
    {
        // Arrange
        $product = new Product('Test Product', 99.99, 'Description', $this->createCategory());
        $product->setAvailable(10);

        $command = new CreateOrderCommand(
            customerName: 'John Doe',
            deliveryAddress: '123 Main St',
            items: [
                ['product_id' => 1, 'quantity' => 2],
            ],
            userId: null
        );

        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->with($this->equalTo(ProductId::fromInt(1)))
            ->willReturn($product);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Order::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->logger
            ->expects($this->atLeast(2))
            ->method('info');

        // Act
        $result = $this->handler($command);

        // Assert
        $this->assertInstanceOf(Order::class, $result);
        $this->assertEquals('John Doe', $result->getCustomerName());
        $this->assertEquals('123 Main St', $result->getDeliveryAddress());
        $this->assertCount(1, $result->getItems());
        $this->assertEquals(8, $product->getAvailable()); // 10 - 2
    }

    public function testCreateOrderWithUser(): void
    {
        // Arrange
        $product = new Product('Test Product', 99.99, 'Description', $this->createCategory());
        $product->setAvailable(5);

        $user = new User('testuser', 'test@example.com', 'password', ['ROLE_CUSTOMER']);

        $command = new CreateOrderCommand(
            customerName: 'John Doe',
            deliveryAddress: '123 Main St',
            items: [
                ['product_id' => 1, 'quantity' => 1],
            ],
            userId: 42
        );

        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->with($this->equalTo(UserId::fromInt(42)))
            ->willReturn($user);

        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($product);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $result = $this->handler($command);

        // Assert
        $this->assertInstanceOf(Order::class, $result);
        $this->assertSame($user, $result->getUser());
    }

    public function testCreateOrderWithMultipleItems(): void
    {
        // Arrange
        $product1 = new Product('Product 1', 50.00, 'Description 1', $this->createCategory());
        $product1->setAvailable(10);

        $product2 = new Product('Product 2', 75.00, 'Description 2', $this->createCategory());
        $product2->setAvailable(8);

        $command = new CreateOrderCommand(
            customerName: 'Jane Doe',
            deliveryAddress: '456 Oak Ave',
            items: [
                ['product_id' => 1, 'quantity' => 2],
                ['product_id' => 2, 'quantity' => 3],
            ]
        );

        $this->productRepository
            ->expects($this->exactly(2))
            ->method('findById')
            ->willReturnOnConsecutiveCalls($product1, $product2);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $result = $this->handler($command);

        // Assert
        $this->assertInstanceOf(Order::class, $result);
        $this->assertCount(2, $result->getItems());
        $this->assertEquals(8, $product1->getAvailable()); // 10 - 2
        $this->assertEquals(5, $product2->getAvailable()); // 8 - 3
    }

    public function testCreateOrderWithProductNotFound(): void
    {
        // Arrange
        $command = new CreateOrderCommand(
            customerName: 'John Doe',
            deliveryAddress: '123 Main St',
            items: [
                ['product_id' => 999, 'quantity' => 1],
            ]
        );

        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product with ID 999 not found');

        // Act
        $this->handler($command);
    }

    public function testCreateOrderWithUnavailableProduct(): void
    {
        // Arrange
        $product = new Product('Test Product', 99.99, 'Description', $this->createCategory());
        $product->setAvailable(0); // Недоступно

        $command = new CreateOrderCommand(
            customerName: 'John Doe',
            deliveryAddress: '123 Main St',
            items: [
                ['product_id' => 1, 'quantity' => 1],
            ]
        );

        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($product);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not available in requested quantity');

        // Act
        $this->handler($command);
    }

    public function testCreateOrderWithInsufficientQuantity(): void
    {
        // Arrange
        $product = new Product('Test Product', 99.99, 'Description', $this->createCategory());
        $product->setAvailable(2);

        $command = new CreateOrderCommand(
            customerName: 'John Doe',
            deliveryAddress: '123 Main St',
            items: [
                ['product_id' => 1, 'quantity' => 5], // Больше чем доступно
            ]
        );

        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($product);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('is not available in requested quantity');

        // Act
        $this->handler($command);
    }

    public function testCreateOrderWithAdditionalInfo(): void
    {
        // Arrange
        $product = new Product('Test Product', 99.99, 'Description', $this->createCategory());
        $product->setAvailable(10);

        $command = new CreateOrderCommand(
            customerName: 'John Doe',
            deliveryAddress: '123 Main St',
            items: [
                ['product_id' => 1, 'quantity' => 1],
            ],
            additionalInfo: 'Please deliver before 6 PM'
        );

        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($product);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $result = $this->handler($command);

        // Assert
        $this->assertInstanceOf(Order::class, $result);
        $this->assertEquals('Please deliver before 6 PM', $result->getAdditionalInfo());
    }

    public function testLoggerContext(): void
    {
        // Arrange
        $product = new Product('Test Product', 99.99, 'Description', $this->createCategory());
        $product->setAvailable(10);

        $command = new CreateOrderCommand(
            customerName: 'Test Customer',
            deliveryAddress: '123 Main St',
            items: [
                ['product_id' => 1, 'quantity' => 1],
            ]
        );

        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($product);

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
                $this->stringContains('Creating order'),
                $this->callback(static fn ($context) => isset($context['customer'])
                    && $context['customer'] === 'Test Customer'
                )
            );
    }

    private function createCategory(): \App\Domain\Shop\Entity\Category
    {
        return new \App\Domain\Shop\Entity\Category('Test Category', 'test-category', 1);
    }
}
