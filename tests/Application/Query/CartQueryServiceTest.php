<?php

declare(strict_types=1);

namespace App\Tests\Application\Query;

use App\Application\Query\CartQueryService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class CartQueryServiceTest extends TestCase
{
    private Connection&MockObject $connection;
    private RequestStack&MockObject $requestStack;
    private SessionInterface&MockObject $session;
    private CartQueryService $service;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->requestStack = $this->createMock(RequestStack::class);
        $this->session = $this->createMock(SessionInterface::class);

        $this->requestStack
            ->method('getSession')
            ->willReturn($this->session);

        $this->service = new CartQueryService(
            $this->requestStack,
            $this->connection
        );
    }

    public function testGetCartDataWithEmptyCart(): void
    {
        $this->session
            ->method('get')
            ->with('cart', [])
            ->willReturn([]);

        $result = $this->service->getCartData();

        $this->assertSame([], $result['items']);
        $this->assertSame(0.0, $result['total']);
    }

    public function testGetCartDataWithProducts(): void
    {
        $this->session
            ->method('get')
            ->with('cart', [])
            ->willReturn([1 => 2, 2 => 1]);

        $this->connection
            ->method('fetchAssociative')
            ->willReturnMap([
                [
                    'SELECT id, title, slug, price, available FROM product WHERE id = :id',
                    ['id' => 1],
                    ['id' => 1, 'title' => 'Product 1', 'slug' => 'product-1', 'price' => '100.00', 'available' => 10],
                ],
                [
                    'SELECT id, title, slug, price, available FROM product WHERE id = :id',
                    ['id' => 2],
                    ['id' => 2, 'title' => 'Product 2', 'slug' => 'product-2', 'price' => '200.00', 'available' => 5],
                ],
            ]);

        $this->connection
            ->method('fetchOne')
            ->willReturnMap([
                [
                    'SELECT path FROM product_image WHERE product_id = :productId ORDER BY position ASC LIMIT 1',
                    ['productId' => 1],
                    'image1.jpg',
                ],
                [
                    'SELECT path FROM product_image WHERE product_id = :productId ORDER BY position ASC LIMIT 1',
                    ['productId' => 2],
                    null,
                ],
            ]);

        $result = $this->service->getCartData();

        $this->assertCount(2, $result['items']);
        $this->assertSame(400.0, $result['total']); // 100*2 + 200*1
        $this->assertSame('image1.jpg', $result['items'][0]->productImage);
        $this->assertNull($result['items'][1]->productImage);
    }

    public function testGetCartDataSkipsNonExistingProducts(): void
    {
        $this->session
            ->method('get')
            ->with('cart', [])
            ->willReturn([1 => 2, 999 => 1]);

        $this->connection
            ->method('fetchAssociative')
            ->willReturnMap([
                [
                    'SELECT id, title, slug, price, available FROM product WHERE id = :id',
                    ['id' => 1],
                    ['id' => 1, 'title' => 'Product 1', 'slug' => 'product-1', 'price' => '100.00', 'available' => 10],
                ],
                [
                    'SELECT id, title, slug, price, available FROM product WHERE id = :id',
                    ['id' => 999],
                    false,
                ],
            ]);

        $this->connection
            ->method('fetchOne')
            ->willReturn(null);

        $result = $this->service->getCartData();

        $this->assertCount(1, $result['items']);
        $this->assertSame(200.0, $result['total']);
    }

    public function testGetCartCount(): void
    {
        $this->session
            ->method('get')
            ->with('cart', [])
            ->willReturn([1 => 2, 2 => 3]);

        $count = $this->service->getCartCount();

        $this->assertSame(5, $count);
    }

    public function testAddToCart(): void
    {
        $this->session
            ->method('get')
            ->with('cart', [])
            ->willReturn([1 => 2]);

        $this->session
            ->expects($this->once())
            ->method('set')
            ->with('cart', [1 => 5]);

        $this->service->addToCart(1, 3);
    }

    public function testUpdateCart(): void
    {
        $this->session
            ->method('get')
            ->with('cart', [])
            ->willReturn([1 => 2, 2 => 3]);

        $this->session
            ->expects($this->once())
            ->method('set')
            ->with('cart', [1 => 10, 2 => 3]);

        $this->service->updateCart(1, 10);
    }

    public function testRemoveFromCart(): void
    {
        $this->session
            ->method('get')
            ->with('cart', [])
            ->willReturn([1 => 2, 2 => 3]);

        $this->session
            ->expects($this->once())
            ->method('set')
            ->with('cart', [2 => 3]);

        $this->service->removeFromCart(1);
    }

    public function testClearCart(): void
    {
        $this->session
            ->expects($this->once())
            ->method('set')
            ->with('cart', []);

        $this->service->clearCart();
    }
}
