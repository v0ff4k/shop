<?php

declare(strict_types=1);

namespace App\Tests\Application\Handler\Cart;

use App\Application\Command\Cart\AddToCartCommand;
use App\Application\Handler\Cart\AddToCartCommandHandler;
use App\Domain\User\Entity\User;
use App\Infrastructure\Cart\CartStorageInterface;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AddToCartCommandHandlerTest extends TestCase
{
    private Connection&MockObject $connection;
    private Security&MockObject $security;
    private RequestStack&MockObject $requestStack;
    private CartStorageInterface&MockObject $cartStorage;
    private SessionInterface&MockObject $session;
    private AddToCartCommandHandler $handler;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->security = $this->createMock(Security::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->cartStorage = $this->createMock(CartStorageInterface::class);
        $this->session = $this->createMock(SessionInterface::class);

        $this->requestStack
            ->method('getSession')
            ->willReturn($this->session);

        $this->handler = new AddToCartCommandHandler(
            $this->connection,
            $this->security,
            $this->requestStack,
            $this->cartStorage,
        );
    }

    public function testAddToCartWithExistingProductForAnonymousUser(): void
    {
        $command = new AddToCartCommand(productId: 1, quantity: 2);

        // Пользователь не авторизован
        $this->security
            ->method('getUser')
            ->willReturn(null);

        $this->session
            ->method('getId')
            ->willReturn('test-session-123');

        $this->connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                'SELECT id, slug, available FROM product WHERE id = :id',
                ['id' => 1]
            )
            ->willReturn(['id' => 1, 'slug' => 'test-product', 'available' => 10]);

        // Ожидаем получение пустой корзины
        $this->cartStorage
            ->expects($this->once())
            ->method('getCart')
            ->with('anonymous_test-session-123')
            ->willReturn([]);

        // Ожидаем сохранение корзины
        $this->cartStorage
            ->expects($this->once())
            ->method('setCart')
            ->with('anonymous_test-session-123', [1 => 2]);

        ($this->handler)($command);
    }

    public function testAddToCartWithExistingProductForAuthenticatedUser(): void
    {
        $command = new AddToCartCommand(productId: 1, quantity: 2);

        // Создаём мок пользователя
        $user = $this->createMock(User::class);
        $user
            ->method('getId')
            ->willReturn(42);

        $this->security
            ->method('getUser')
            ->willReturn($user);

        $this->connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                'SELECT id, slug, available FROM product WHERE id = :id',
                ['id' => 1]
            )
            ->willReturn(['id' => 1, 'slug' => 'test-product', 'available' => 10]);

        // Ожидаем получение корзины пользователя
        $this->cartStorage
            ->expects($this->once())
            ->method('getCart')
            ->with('user_42')
            ->willReturn([]);

        // Ожидаем сохранение корзины пользователя
        $this->cartStorage
            ->expects($this->once())
            ->method('setCart')
            ->with('user_42', [1 => 2]);

        ($this->handler)($command);
    }

    public function testAddToCartIncrementsExistingQuantity(): void
    {
        $command = new AddToCartCommand(productId: 1, quantity: 3);

        // Пользователь не авторизован
        $this->security
            ->method('getUser')
            ->willReturn(null);

        $this->session
            ->method('getId')
            ->willReturn('test-session-456');

        $this->connection
            ->method('fetchAssociative')
            ->willReturn(['id' => 1, 'slug' => 'test-product', 'available' => 10]);

        // В корзине уже есть товар 1 в количестве 2
        $this->cartStorage
            ->method('getCart')
            ->with('anonymous_test-session-456')
            ->willReturn([1 => 2]);

        // Ожидаем обновление количества до 5
        $this->cartStorage
            ->expects($this->once())
            ->method('setCart')
            ->with('anonymous_test-session-456', [1 => 5]);

        ($this->handler)($command);
    }

    public function testAddToCartThrowsNotFoundForNonExistingProduct(): void
    {
        $command = new AddToCartCommand(productId: 999, quantity: 1);

        $this->security
            ->method('getUser')
            ->willReturn(null);

        $this->connection
            ->method('fetchAssociative')
            ->willReturn(false);

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessage('Product with id "999" not found');

        ($this->handler)($command);
    }

    public function testAddToCartThrowsExceptionWhenNotEnoughStock(): void
    {
        $command = new AddToCartCommand(productId: 1, quantity: 100);

        $this->security
            ->method('getUser')
            ->willReturn(null);

        $this->connection
            ->method('fetchAssociative')
            ->willReturn(['id' => 1, 'slug' => 'test-product', 'available' => 5]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Not enough products in stock. Available: 5, Requested: 100');

        ($this->handler)($command);
    }
}
