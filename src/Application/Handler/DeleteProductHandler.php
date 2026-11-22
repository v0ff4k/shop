<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\DeleteProductCommand;
use App\Domain\Shop\Repository\ProductRepositoryInterface;
use App\Domain\Shop\ValueObject\ProductId;
use App\Infrastructure\Cache\CacheInvalidationService;
use App\Infrastructure\Search\ProductIndexer;
use App\Infrastructure\Storage\FileUploadService;
use Psr\Log\LoggerInterface;

/**
 * Обработчик команды удаления продукта.
 */
final class DeleteProductHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly FileUploadService $fileUploadService,
        private readonly LoggerInterface $logger,
        private readonly CacheInvalidationService $cacheInvalidation,
        private readonly ?ProductIndexer $productIndexer = null
    ) {
    }

    public function __invoke(DeleteProductCommand $command): void
    {
        $startedAt = microtime(true);
        $product = $this->productRepository->findById(ProductId::fromInt($command->productId));

        if ($product === null) {
            throw new \InvalidArgumentException('Product not found');
        }

        $this->logger->info('Deleting product', ['id' => $product->getId()]);

        // Получаем категорию перед удалением (для инвалидации кэша)
        $categoryId = $product->getCategory()->getId();

        // Удаляем изображения
        foreach ($product->getImages() as $image) {
            try {
                $this->fileUploadService->deleteFile($image->getPath(), 'products');
                $this->logger->info('Image deleted', ['filename' => $image->getPath()]);
            } catch (\Exception $e) {
                $this->logger->error('Failed to delete image', ['error' => $e->getMessage()]);
            }
        }

        // Удаляем продукт (каскадно удалит изображения из БД)
        $this->productRepository->remove($product);

        // Инвалидируем кэш
        $this->cacheInvalidation->invalidateProductDeleted(
            $product->getId(),
            $categoryId->toInt()
        );

        // Удаляем из индекса Elasticsearch (best effort)
        if ($this->productIndexer !== null) {
            try {
                $this->productIndexer->removeProduct((int) $command->productId);
            } catch (\Throwable $e) {
                $this->logger->warning('Product deindex failed on delete', [
                    'product_id' => $command->productId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Product deleted', [
            'id' => $product->getId(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);
    }
}
