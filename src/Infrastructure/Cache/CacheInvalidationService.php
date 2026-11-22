<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use Psr\Log\LoggerInterface;

/**
 * Сервис для управления инвалидацией кэша.
 *
 * Предоставляет методы для инвалидации кэша при изменениях данных
 */
final class CacheInvalidationService
{
    // Теги для группировки кэшей
    public const TAG_PRODUCT_LIST = 'product_list';
    public const TAG_PRODUCT_DETAIL = 'product_detail';
    public const TAG_CATEGORY_LIST = 'category_list';
    public const TAG_CATEGORY_DETAIL = 'category_detail';
    public const TAG_CART = 'cart';
    public const TAG_ORDER = 'order';
    public const TAG_USER = 'user';

    // Паттерны ключей
    private const KEY_PRODUCT_DETAIL = 'product:detail:%d';
    private const KEY_CATEGORY_DETAIL = 'category:detail:%d';
    private const KEY_CART_USER = 'cart:user:%d';
    private const KEY_ORDER_DETAIL = 'order:detail:%s';

    public function __construct(
        private readonly CacheServiceInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Инвалидировать кэш при создании продукта.
     */
    public function invalidateProductCreated(int $productId, int $categoryId): void
    {
        $this->logger->info('Invalidating cache: product created', ['product_id' => $productId]);

        // Инвалидируем списки продуктов
        $this->cache->invalidateByTag(self::TAG_PRODUCT_LIST);

        // Инвалидируем кэш категории
        $this->invalidateCategory($categoryId);
    }

    /**
     * Инвалидировать по произвольному тегу.
     */
    public function invalidateByTag(string $tag): void
    {
        $this->logger->info('Invalidating cache by tag', ['tag' => $tag]);

        $this->cache->invalidateByTag($tag);
    }

    public function invalidateByKey(string $key): void
    {
        $this->logger->info('Invalidating cache by key', ['key' => $key]);

        $this->cache->delete($key);
    }

    public function invalidatePattern(string $pattern): void
    {
        $this->logger->info('Invalidating cache by pattern', ['pattern' => $pattern]);

        $this->cache->deleteByPattern($pattern);
    }

    /**
     * Инвалидировать кэш категории.
     */
    public function invalidateCategory(int $categoryId): void
    {
        $this->logger->info('Invalidating cache: category', ['category_id' => $categoryId]);

        // Инвалидируем конкретную категорию
        $this->cache->delete(\sprintf(self::KEY_CATEGORY_DETAIL, $categoryId));
        $this->cache->invalidateByTag(self::TAG_CATEGORY_DETAIL);

        // Инвалидируем списки категорий
        $this->cache->invalidateByTag(self::TAG_CATEGORY_LIST);

        // Инвалидируем списки продуктов в этой категории
        $this->cache->invalidateByTag(self::TAG_PRODUCT_LIST);
    }

    /**
     * Инвалидировать кэш при обновлении продукта.
     */
    public function invalidateProductUpdated(int $productId, ?int $categoryId = null): void
    {
        $this->logger->info('Invalidating cache: product updated', ['product_id' => $productId]);

        // Инвалидируем конкретный продукт
        $this->cache->delete(\sprintf(self::KEY_PRODUCT_DETAIL, $productId));

        // Инвалидируем списки
        $this->cache->invalidateByTag(self::TAG_PRODUCT_LIST);

        // Если категория изменилась - инвалидируем старую и новую
        if ($categoryId !== null) {
            $this->invalidateCategory($categoryId);
        }
    }

    /**
     * Инвалидировать кэш при удалении продукта.
     */
    public function invalidateProductDeleted(int $productId, int $categoryId): void
    {
        $this->logger->info('Invalidating cache: product deleted', ['product_id' => $productId]);

        // Инвалидируем конкретный продукт
        $this->cache->delete(\sprintf(self::KEY_PRODUCT_DETAIL, $productId));

        // Инвалидируем списки продуктов
        $this->cache->invalidateByTag(self::TAG_PRODUCT_LIST);

        // Инвалидируем кэш категории
        $this->invalidateCategory($categoryId);
    }

    /**
     * Инвалидировать кэш при изменении остатков продукта.
     */
    public function invalidateProductStock(int $productId): void
    {
        $this->logger->info('Invalidating cache: product stock changed', ['product_id' => $productId]);

        // Инвалидируем конкретный продукт (так как там есть available)
        $this->cache->delete(\sprintf(self::KEY_PRODUCT_DETAIL, $productId));

        // Инвалидируем списки (так как там может быть фильтрация по available)
        $this->cache->invalidateByTag(self::TAG_PRODUCT_LIST);
    }

    /**
     * Инвалидировать кэш корзины пользователя.
     */
    public function invalidateCart(int $userId): void
    {
        $this->logger->info('Invalidating cache: cart', ['user_id' => $userId]);

        $this->cache->delete(\sprintf(self::KEY_CART_USER, $userId));
        $this->cache->invalidateByTag(self::TAG_CART);
    }

    /**
     * Инвалидировать кэш заказа.
     */
    public function invalidateOrder(string $orderNumber): void
    {
        $this->logger->info('Invalidating cache: order', ['order_number' => $orderNumber]);

        $this->cache->delete(\sprintf(self::KEY_ORDER_DETAIL, $orderNumber));
        $this->cache->invalidateByTag(self::TAG_ORDER);
    }

    /**
     * Инвалидировать кэш пользователя.
     */
    public function invalidateUser(int $userId): void
    {
        $this->logger->info('Invalidating cache: user', ['user_id' => $userId]);

        $this->cache->invalidateByTag(self::TAG_USER);
    }

    /**
     * Полная инвалидация (при массовых изменениях).
     */
    public function invalidateAll(): void
    {
        $this->logger->warning('Invalidating all cache');

        $this->cache->clear();
    }

    /**
     * Получить ключ для продукта.
     */
    public function getProductKey(int $productId): string
    {
        return \sprintf(self::KEY_PRODUCT_DETAIL, $productId);
    }

    /**
     * Получить ключ для категории.
     */
    public function getCategoryKey(int $categoryId): string
    {
        return \sprintf(self::KEY_CATEGORY_DETAIL, $categoryId);
    }

    /**
     * Получить ключ для корзины.
     */
    public function getCartKey(int $userId): string
    {
        return \sprintf(self::KEY_CART_USER, $userId);
    }
}
