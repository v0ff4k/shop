<?php

declare(strict_types=1);

namespace App\Infrastructure\Cart;

/**
 * Интерфейс хранилища корзины.
 *
 * Позволяет менять способ хранения корзины (Redis, DB, Session и т.д.)
 */
interface CartStorageInterface
{
    /**
     * Получить корзину по идентификатору.
     *
     * @return array<int, int> [productId => quantity, ...]
     */
    public function getCart(string $identifier): array;

    /**
     * Сохранить корзину.
     *
     * @param array<int, int> $cart [productId => quantity, ...]
     */
    public function setCart(string $identifier, array $cart): void;

    /**
     * Очистить корзину.
     */
    public function clearCart(string $identifier): void;

    /**
     * Получить количество товаров в корзине.
     */
    public function getCartCount(string $identifier): int;

    /**
     * Объединить две корзины.
     *
     * @param string $sourceIdentifier Идентификатор исходной корзины (например, анонимной)
     * @param string $targetIdentifier Идентификатор целевой корзины (например, пользователя)
     */
    public function mergeCarts(string $sourceIdentifier, string $targetIdentifier): void;
}
