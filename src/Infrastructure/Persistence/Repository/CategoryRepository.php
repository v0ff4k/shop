<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\Repository\CategoryRepositoryInterface;
use App\Domain\Shop\ValueObject\CategoryId;
use App\Infrastructure\Cache\CacheInvalidationService;
use App\Infrastructure\Cache\CacheServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;

/**
 * Реализация репозитория категорий (Doctrine ORM).
 */
final class CategoryRepository implements CategoryRepositoryInterface
{
    private const CACHE_TTL = 600;
    private const CACHE_KEY_BY_ID = 'category:detail:%d';
    private const CACHE_KEY_BY_SLUG = 'category:slug:%s';
    private const CACHE_KEY_ALL = 'category:all';
    private const CACHE_KEY_ROOT = 'category:root';
    private const CACHE_KEY_SUBCATEGORIES = 'category:children:%d';

    private ObjectRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CacheServiceInterface $cache,
        private readonly CacheInvalidationService $cacheInvalidation
    ) {
        $this->repository = $entityManager->getRepository(Category::class);
    }

    public function findById(CategoryId $id): ?Category
    {
        $cacheKey = \sprintf(self::CACHE_KEY_BY_ID, $id->value());
        $cached = $this->cache->get($cacheKey);

        if ($cached instanceof Category) {
            return $cached;
        }

        $category = $this->repository->find($id->value());

        if ($category !== null) {
            $this->cache->setWithTag(
                $cacheKey,
                $category,
                CacheInvalidationService::TAG_CATEGORY_DETAIL,
                self::CACHE_TTL
            );
        }

        return $category;
    }

    public function findBySlug(string $slug): ?Category
    {
        $cacheKey = \sprintf(self::CACHE_KEY_BY_SLUG, $slug);
        $cached = $this->cache->get($cacheKey);

        if ($cached instanceof Category) {
            return $cached;
        }

        $category = $this->repository->findOneBy(['slug' => $slug]);

        if ($category !== null) {
            $this->cache->setWithTag(
                $cacheKey,
                $category,
                CacheInvalidationService::TAG_CATEGORY_DETAIL,
                self::CACHE_TTL
            );
        }

        return $category;
    }

    public function findAll(): array
    {
        $cached = $this->cache->get(self::CACHE_KEY_ALL);

        if (\is_array($cached)) {
            return $cached;
        }

        $categories = $this->repository->findAll();
        $this->cache->setWithTag(
            self::CACHE_KEY_ALL,
            $categories,
            CacheInvalidationService::TAG_CATEGORY_LIST,
            self::CACHE_TTL
        );

        return $categories;
    }

    public function findRootCategories(): array
    {
        $cached = $this->cache->get(self::CACHE_KEY_ROOT);

        if (\is_array($cached)) {
            return $cached;
        }

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('c')
            ->from(Category::class, 'c')
            ->where('c.parent IS NULL')
            ->orderBy('c.title', 'ASC');

        $categories = $qb->getQuery()->getResult();
        $this->cache->setWithTag(
            self::CACHE_KEY_ROOT,
            $categories,
            CacheInvalidationService::TAG_CATEGORY_LIST,
            self::CACHE_TTL
        );

        return $categories;
    }

    public function findSubcategories(int $parentId): array
    {
        $cacheKey = \sprintf(self::CACHE_KEY_SUBCATEGORIES, $parentId);
        $cached = $this->cache->get($cacheKey);

        if (\is_array($cached)) {
            return $cached;
        }

        $categories = $this->repository->findBy(['parent' => $parentId], ['title' => 'ASC']);
        $this->cache->setWithTag(
            $cacheKey,
            $categories,
            CacheInvalidationService::TAG_CATEGORY_LIST,
            self::CACHE_TTL
        );

        return $categories;
    }

    public function save(Category $category): void
    {
        $this->entityManager->persist($category);
        $this->entityManager->flush();

        $categoryId = $category->getId();
        if ($categoryId !== null) {
            $this->cacheInvalidation->invalidateCategory($categoryId);
        }
    }

    public function remove(Category $category): void
    {
        $categoryId = $category->getId();
        $this->entityManager->remove($category);
        $this->entityManager->flush();

        if ($categoryId !== null) {
            $this->cacheInvalidation->invalidateCategory($categoryId);
        }
    }

    public function count(): int
    {
        return $this->repository->count([]);
    }
}
