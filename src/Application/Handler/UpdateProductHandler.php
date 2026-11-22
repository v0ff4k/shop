<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\UpdateProductCommand;
use App\Domain\Shop\Repository\CategoryRepositoryInterface;
use App\Domain\Shop\Repository\ProductRepositoryInterface;
use App\Domain\Shop\ValueObject\CategoryId;
use App\Domain\Shop\ValueObject\ProductId;
use App\Infrastructure\Cache\CacheInvalidationService;
use App\Infrastructure\Search\ProductIndexer;
use App\Infrastructure\Storage\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Обработчик команды обновления продукта.
 */
final class UpdateProductHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly FileUploadService $fileUploadService,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly LoggerInterface $logger,
        private readonly CacheInvalidationService $cacheInvalidation,
        private readonly ?ProductIndexer $productIndexer = null
    ) {
    }

    public function __invoke(UpdateProductCommand $command): void
    {
        $startedAt = microtime(true);
        $product = $this->productRepository->findById(ProductId::fromInt($command->productId));

        if ($product === null) {
            throw new \InvalidArgumentException('Product not found');
        }

        $this->logger->info('Updating product', ['id' => $product->getId()]);

        // Запоминаем старые значения для инвалидации
        $oldAvailable = $product->getAvailable();

        // Обновляем поля
        if ($command->title !== null) {
            $product->setTitle($command->title);
        }

        if ($command->price !== null) {
            $product->setPrice($command->price);
        }

        if ($command->description !== null) {
            $product->setDescription($command->description);
        }

        if ($command->available !== null) {
            $product->setAvailable($command->available);
        }

        if ($command->categoryId !== null) {
            $category = $this->categoryRepository->findById(CategoryId::fromInt($command->categoryId));
            if ($category !== null) {
                $product->setCategory($category);
            }
        }

        $this->entityManager->flush();

        // Инвалидируем кэш при изменении данных
        $this->cacheInvalidation->invalidateProductUpdated(
            $product->getId(),
            $product->getCategory()->getId()->toInt()
        );

        // Если изменились остатки - отдельная инвалидация
        if ($command->available !== null && $oldAvailable !== $command->available) {
            $this->cacheInvalidation->invalidateProductStock($product->getId());
        }

        // Синхронизируем индекс Elasticsearch (не блокируем update при ошибке индекса)
        if ($this->productIndexer !== null) {
            try {
                $this->productIndexer->updateProduct(
                    (int) $product->getId(),
                    [
                        'title' => $product->getTitle(),
                        'description' => $product->getDescription(),
                        'slug' => $product->getSlug(),
                        'price' => $product->getPrice()->amountFloat(),
                        'category_id' => $product->getCategory()->getId()->toInt(),
                        'available' => $product->getAvailable(),
                        'added' => $product->getAdded()->format('Y-m-d H:i:s'),
                        'viewed' => $product->getViewed(),
                    ]
                );
            } catch (\Throwable $e) {
                $this->logger->warning('Product indexing failed on update', [
                    'product_id' => $product->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Product updated', [
            'id' => $product->getId(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
