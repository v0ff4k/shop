<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Shop\Entity\Product;
use App\Domain\Shop\Repository\ProductRepositoryInterface;
use App\Domain\Shop\ValueObject\ProductId;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;

/**
 * Реализация репозитория продуктов (Doctrine ORM)
 * Используется для операций записи (CQRS - Command side).
 */
final class ProductRepository implements ProductRepositoryInterface
{
    private ObjectRepository $repository;

    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        $this->repository = $entityManager->getRepository(Product::class);
    }

    public function findById(ProductId $id): ?Product
    {
        return $this->repository->find($id->value());
    }

    /**
     * Найти продукт с основными полями (без загрузки связанных сущностей)
     * Используется для корзины чтобы избежать циклических ссылок.
     */
    public function findForCart(int $productId): ?array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('p.id', 'p.title', 'p.slug', 'p.price', 'p.available')
            ->from(Product::class, 'p')
            ->where('p.id = :id')
            ->setParameter('id', $productId);

        $result = $qb->getQuery()->getArrayResult();

        return $result[0] ?? null;
    }

    /**
     * Получить путь первого изображения продукта.
     */
    public function findFirstImagePath(int $productId): ?string
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('pi.path')
            ->from(\App\Domain\Shop\Entity\ProductImage::class, 'pi')
            ->where('pi.product = :productId')
            ->setParameter('productId', $productId)
            ->orderBy('pi.position', 'ASC')
            ->setMaxResults(1);

        $result = $qb->getQuery()->getArrayResult();

        return $result[0]['path'] ?? null;
    }

    public function findBySlug(string $slug): ?Product
    {
        return $this->repository->findOneBy(['slug' => $slug]);
    }

    public function findAll(int $limit = 50, int $offset = 0): array
    {
        return $this->repository->findBy([], ['added' => 'DESC'], $limit, $offset);
    }

    public function findByCategory(int $categoryId, int $limit = 50, int $offset = 0): array
    {
        return $this->repository->findBy(
            ['category' => $categoryId],
            ['added' => 'DESC'],
            $limit,
            $offset
        );
    }

    public function findNew(int $limit = 10): array
    {
        return $this->repository->findBy([], ['added' => 'DESC'], $limit);
    }

    public function findPopular(int $limit = 10): array
    {
        return $this->repository->findBy([], ['viewed' => 'DESC'], $limit);
    }

    public function search(string $query, int $limit = 50): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('p')
            ->from(Product::class, 'p')
            ->where('p.title LIKE :query OR p.description LIKE :query')
            ->setParameter('query', '%'.$query.'%')
            ->orderBy('p.added', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    public function save(Product $product): void
    {
        $this->entityManager->persist($product);
        $this->entityManager->flush();
    }

    public function remove(Product $product): void
    {
        $this->entityManager->remove($product);
        $this->entityManager->flush();
    }

    public function countByCategory(int $categoryId): int
    {
        return $this->repository->count(['category' => $categoryId]);
    }

    public function count(): int
    {
        return $this->repository->count([]);
    }
}
