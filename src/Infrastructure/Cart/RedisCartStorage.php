<?php

declare(strict_types=1);

namespace App\Infrastructure\Cart;

use Redis;

/**
 * Хранение корзины в Redis.
 *
 * Корзина сохраняется независимо от Symfony cache и сессий.
 * Данные корзины живут 30 дней с последнего обновления.
 *
 * Идентификатор корзины:
 * - Для авторизованных: "user:{userId}"
 * - Для анонимных: "anonymous:{sessionId}"
 */
final class RedisCartStorage implements CartStorageInterface
{
    private const CART_TTL = 2_592_000; // 30 дней
    private const CART_PREFIX = 'cart:';

    public function __construct(
        private readonly \Redis $redis
    ) {
    }

    /**
     * Получить корзину по идентификатору.
     */
    public function getCart(string $identifier): array
    {
        $key = $this->getCartKey($identifier);
        $data = $this->redis->get($key);

        if ($data === false) {
            return [];
        }

        $cart = json_decode($data, true);

        return \is_array($cart) ? $cart : [];
    }

    /**
     * Сохранить корзину.
     */
    public function setCart(string $identifier, array $cart): void
    {
        $key = $this->getCartKey($identifier);
        $this->redis->setEx($key, self::CART_TTL, json_encode($cart, \JSON_THROW_ON_ERROR));
    }

    /**
     * Очистить корзину.
     */
    public function clearCart(string $identifier): void
    {
        $this->redis->del($this->getCartKey($identifier));
    }

    /**
     * Получить количество товаров в корзине.
     */
    public function getCartCount(string $identifier): int
    {
        $cart = $this->getCart($identifier);

        return array_sum($cart);
    }

    /**
     * Объединить две корзины.
     *
     * Товары из исходной корзины добавляются к целевой.
     * Если товар есть в обеих корзинах, количества суммируются.
     * Исходная корзина очищается после объединения.
     */
    public function mergeCarts(string $sourceIdentifier, string $targetIdentifier): void
    {
        $sourceCart = $this->getCart($sourceIdentifier);
        $targetCart = $this->getCart($targetIdentifier);

        // Объединяем количества
        foreach ($sourceCart as $productId => $quantity) {
            $targetCart[$productId] = ($targetCart[$productId] ?? 0) + $quantity;
        }

        // Сохраняем объединённую корзину
        if (!empty($targetCart)) {
            $this->setCart($targetIdentifier, $targetCart);
        }

        // Удаляем исходную корзину
        $this->clearCart($sourceIdentifier);
    }

    private function getCartKey(string $identifier): string
    {
        return self::CART_PREFIX.$identifier;
    }
}
