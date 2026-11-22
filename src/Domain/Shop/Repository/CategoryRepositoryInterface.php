<?php

declare(strict_types=1);

namespace App\Domain\Shop\Repository;

use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\ValueObject\CategoryId;

/**
 * Интерфейс репозитория категорий.
 */
interface CategoryRepositoryInterface
{
    public function findById(CategoryId $id): ?Category;

    public function findBySlug(string $slug): ?Category;

    public function findAll(): array;

    public function findRootCategories(): array;

    public function findSubcategories(int $parentId): array;

    public function save(Category $category): void;

    public function remove(Category $category): void;

    public function count(): int;
}
