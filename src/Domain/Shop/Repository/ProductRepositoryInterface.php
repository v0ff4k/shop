<?php

declare(strict_types=1);

namespace App\Domain\Shop\Repository;

use App\Domain\Shop\Entity\Product;
use App\Domain\Shop\ValueObject\ProductId;

/**
 * Интерфейс репозитория продуктов.
 */
interface ProductRepositoryInterface
{
    public function findById(ProductId $id): ?Product;

    /**
     * Найти продукт с основными полями (без загрузки связанных сущностей).
     */
    public function findForCart(int $productId): ?array;

    /**
     * Получить путь первого изображения продукта.
     */
    public function findFirstImagePath(int $productId): ?string;

    public function findBySlug(string $slug): ?Product;

    public function findAll(int $limit = 50, int $offset = 0): array;

    public function findByCategory(int $categoryId, int $limit = 50, int $offset = 0): array;

    public function findNew(int $limit = 10): array;

    public function findPopular(int $limit = 10): array;

    public function search(string $query, int $limit = 50): array;

    public function save(Product $product): void;

    public function remove(Product $product): void;

    public function count(): int;

    public function countByCategory(int $categoryId): int;
}
