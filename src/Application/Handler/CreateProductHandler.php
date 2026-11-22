<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\CreateProductCommand;
use App\Application\Command\ProcessProductImageCommand;
use App\Domain\Shop\Entity\Product;
use App\Domain\Shop\Repository\CategoryRepositoryInterface;
use App\Domain\Shop\ValueObject\CategoryId;
use App\Infrastructure\Cache\CacheInvalidationService;
use App\Infrastructure\Search\ProductIndexer;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Обработчик команды создания продукта.
 */
final class CreateProductHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly MessageBusInterface $imageBus,
        private readonly LoggerInterface $logger,
        private readonly CacheInvalidationService $cacheInvalidation,
        private readonly ?ProductIndexer $productIndexer = null
    ) {
    }

    public function __invoke(CreateProductCommand $command): Product
    {
        $startedAt = microtime(true);
        $this->logger->info('Creating product', ['title' => $command->title]);

        // Получаем категорию
        $category = $this->categoryRepository->findById(
            CategoryId::fromInt($command->categoryId)
        );

        if ($category === null) {
            throw new \InvalidArgumentException('Category not found');
        }

        // Создаем продукт
        $product = new Product(
            $command->title,
            $command->price,
            $command->description,
            $category
        );

        $product->setAvailable($command->available);

        // Сохраняем продукт сначала
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        // Инвалидируем кэш после создания
        $this->cacheInvalidation->invalidateProductCreated(
            (int) $product->getId(),
            $category->getId()->toInt()
        );

        // Синхронизируем индекс Elasticsearch (не блокируем основной flow при ошибках)
        if ($this->productIndexer !== null) {
            try {
                $this->productIndexer->indexProduct($this->mapProductForIndex($product));
            } catch (\Throwable $e) {
                $this->logger->warning('Product indexing failed on create', [
                    'product_id' => $product->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Отправляем изображения на асинхронную обработку
        if ($command->imageData !== null) {
            foreach ($command->imageData as $imageInfo) {
                $this->imageBus->dispatch(
                    new ProcessProductImageCommand(
                        (string) $product->getId(),
                        $imageInfo['path'],
                        $imageInfo['original_name'],
                        $imageInfo['position']
                    )
                );

                $this->logger->info(
                    'Image processing queued',
                    [
                        'product' => $product->getId(),
                        'file' => $imageInfo['original_name'],
                    ]
                );
            }
        }

        $this->logger->info('Product created', [
            'id' => $product->getId(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return $product;
    }

    /**
     * @return array<string, mixed>
     */
    private function mapProductForIndex(Product $product): array
    {
        return [
            'id' => (int) $product->getId(),
            'title' => $product->getTitle(),
            'description' => $product->getDescription(),
            'slug' => $product->getSlug(),
            'price' => $product->getPrice()->amountFloat(),
            'category_id' => $product->getCategory()->getId()->toInt(),
            'available' => $product->getAvailable(),
            'added' => $product->getAdded()->format('Y-m-d H:i:s'),
            'viewed' => $product->getViewed(),
        ];
    }
}
