<?php

declare(strict_types=1);

namespace App\UI\Factory;

use App\Application\DTO\Response\CategoryResponse;
use App\Application\DTO\Response\OrderItemResponse;
use App\Application\DTO\Response\OrderResponse;
use App\Application\DTO\Response\PaginatedResponse;
use App\Application\DTO\Response\ProductImageResponse;
use App\Application\DTO\Response\ProductResponse;
use App\Application\DTO\Response\UserResponse;
use App\Domain\Order\Entity\Order;
use App\Domain\Order\Entity\OrderItem;
use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\Entity\Product;
use App\Domain\Shop\Entity\ProductImage;
use App\Domain\User\Entity\User;

/**
 * Фабрика для создания Response DTO из Entity.
 */
final class ResponseFactory
{
    /**
     * Создать массив ProductResponse из массива Product.
     *
     * @param array<Product> $products
     *
     * @return array<ProductResponse>
     */
    public static function productsFromEntities(array $products): array
    {
        return array_map(
            static fn (Product $product) => self::productFromEntity($product),
            $products
        );
    }

    /**
     * Создать ProductResponse из Product Entity.
     */
    public static function productFromEntity(Product $product): ProductResponse
    {
        return ProductResponse::fromEntity($product);
    }

    /**
     * Создать массив CategoryResponse из массива Category.
     *
     * @param array<Category> $categories
     *
     * @return array<CategoryResponse>
     */
    public static function categoriesFromEntities(array $categories, bool $withChildren = false): array
    {
        return array_map(
            static fn (Category $category) => self::categoryFromEntity($category, 0, $withChildren),
            $categories
        );
    }

    /**
     * Создать CategoryResponse из Category Entity.
     */
    public static function categoryFromEntity(
        Category $category,
        int $productsCount = 0,
        bool $withChildren = false
    ): CategoryResponse {
        return CategoryResponse::fromEntity($category, $productsCount, $withChildren);
    }

    /**
     * Создать ProductImageResponse из ProductImage Entity.
     */
    public static function productImageFromEntity(ProductImage $image): ProductImageResponse
    {
        return ProductImageResponse::fromEntity($image);
    }

    /**
     * Создать UserResponse из User Entity.
     */
    public static function userFromEntity(User $user): UserResponse
    {
        return UserResponse::fromEntity($user);
    }

    /**
     * Создать OrderResponse из Order Entity.
     */
    public static function orderFromEntity(Order $order): OrderResponse
    {
        return OrderResponse::fromEntity($order);
    }

    /**
     * Создать OrderItemResponse из OrderItem Entity.
     */
    public static function orderItemFromEntity(OrderItem $item): OrderItemResponse
    {
        return OrderItemResponse::fromEntity($item);
    }

    /**
     * Создать пагинированный ответ
     *
     * @template T
     *
     * @param array<T> $items
     *
     * @return PaginatedResponse<T>
     */
    public static function paginatedResponse(array $items, int $total, int $page, int $limit): PaginatedResponse
    {
        return PaginatedResponse::create($items, $total, $page, $limit);
    }

    /**
     * Создать пагинированный ответ с маппингом
     *
     * @template T
     * @template U
     *
     * @param array<T> $items
     * @param callable(T): U $mapper
     *
     * @return PaginatedResponse<U>
     */
    public static function paginatedResponseWithMapping(
        array $items,
        callable $mapper,
        int $total,
        int $page,
        int $limit
    ): PaginatedResponse {
        $mappedItems = array_map($mapper, $items);

        return PaginatedResponse::create($mappedItems, $total, $page, $limit);
    }
}
