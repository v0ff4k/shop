<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Application\Query\DTO\CartItemDTO;
use App\Infrastructure\Cart\RedisCartStorage;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Query сервис для работы с корзиной.
 *
 * Хранит корзину в Redis (независимо от Symfony cache/сессий).
 * Данные корзины сохраняются 30 дней с последнего обновления.
 *
 * Идентификатор корзины:
 * - Для авторизованных: cart:user_{userId} - доступна со всех устройств
 * - Для анонимных: cart:anonymous_{sessionId} - привязана к сессии
 */
final class CartQueryService
{
    private const CART_USER_PREFIX = 'user_';
    private const CART_ANON_PREFIX = 'anonymous_';

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly Connection $connection,
        private readonly RedisCartStorage $cartStorage
    ) {
    }

    /**
     * Получить данные корзины.
     *
     * @return array{items: array, total: float}
     */
    public function getCartData(): array
    {
        $cartIdentifier = $this->getCartIdentifier();
        $cart = $this->cartStorage->getCart($cartIdentifier);

        $items = [];
        $total = 0.0;

        foreach ($cart as $productId => $quantity) {
            // Прямой SQL запрос без загрузки entity
            $product = $this->connection->fetchAssociative(
                'SELECT id, title, slug, price, available FROM product WHERE id = :id',
                ['id' => $productId]
            );

            if ($product) {
                // Получаем первое изображение
                $imagePath = $this->connection->fetchFirstColumn(
                    'SELECT path FROM product_image WHERE product_id = :productId ORDER BY position ASC LIMIT 1',
                    ['productId' => $productId]
                );
                $imagePath = $imagePath[0] ?? null;

                $price = (float) $product['price'];
                $subtotal = $price * $quantity;

                $items[] = new CartItemDTO(
                    productId: (int) $product['id'],
                    productTitle: $product['title'],
                    productSlug: $product['slug'],
                    productPrice: $product['price'],
                    productAvailable: (int) $product['available'],
                    productImage: $imagePath ?: null,
                    quantity: $quantity,
                    subtotal: $subtotal,
                );

                $total += $subtotal;
            }
        }

        return [
            'items' => $items,
            'total' => $total,
        ];
    }

    /**
     * Получить количество товаров в корзине.
     */
    public function getCartCount(): int
    {
        return $this->cartStorage->getCartCount($this->getCartIdentifier());
    }

    /**
     * Добавить товар в корзину.
     */
    public function addToCart(int $productId, int $quantity): void
    {
        $cartIdentifier = $this->getCartIdentifier();
        $cart = $this->cartStorage->getCart($cartIdentifier);

        $cart[$productId] = ($cart[$productId] ?? 0) + $quantity;
        $this->cartStorage->setCart($cartIdentifier, $cart);
    }

    /**
     * Обновить количество товара в корзине.
     */
    public function updateCart(int $productId, int $quantity): void
    {
        $cartIdentifier = $this->getCartIdentifier();
        $cart = $this->cartStorage->getCart($cartIdentifier);

        $cart[$productId] = $quantity;
        $this->cartStorage->setCart($cartIdentifier, $cart);
    }

    /**
     * Удалить товар из корзины.
     */
    public function removeFromCart(int $productId): void
    {
        $cartIdentifier = $this->getCartIdentifier();
        $cart = $this->cartStorage->getCart($cartIdentifier);

        unset($cart[$productId]);
        $this->cartStorage->setCart($cartIdentifier, $cart);
    }

    /**
     * Очистить корзину.
     */
    public function clearCart(): void
    {
        $this->cartStorage->clearCart($this->getCartIdentifier());
    }

    /**
     * Получить идентификатор корзины.
     *
     * Для авторизованных пользователей - user_{userId}
     * Для анонимных пользователей - anonymous_{sessionId}
     */
    private function getCartIdentifier(): string
    {
        $user = $this->security->getUser();

        if ($user !== null) {
            return self::CART_USER_PREFIX.$user->getId();
        }

        $sessionId = $this->requestStack->getSession()?->getId() ?? bin2hex(random_bytes(16));

        return self::CART_ANON_PREFIX.$sessionId;
    }
}
