<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\ProcessProductImageCommand;
use App\Domain\Shop\Entity\ProductImage;
use App\Domain\Shop\Repository\ProductRepositoryInterface;
use App\Domain\Shop\ValueObject\ProductId;
use App\Infrastructure\Storage\FileUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Обработчик команды асинхронной обработки изображения продукта.
 */
final class ProcessProductImageHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly FileUploadService $fileUploadService,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function __invoke(ProcessProductImageCommand $command): void
    {
        $startedAt = microtime(true);
        $this->logger->info(
            'Processing product image',
            [
                'product_id' => $command->productId,
                'file' => $command->originalName,
            ]
        );

        $product = $this->productRepository->findById(
            ProductId::fromInt((int) $command->productId)
        );

        if ($product === null) {
            $this->logger->error(
                'Product not found for image processing',
                [
                    'product_id' => $command->productId,
                ]
            );

            return;
        }

        try {
            // Создаем UploadedFile из сохраненного временного файла
            $uploadedFile = new UploadedFile(
                $command->filePath,
                $command->originalName,
                null,
                null,
                true
            );

            $this->fileUploadService->validate($uploadedFile);
            $filename = $this->fileUploadService->uploadProductImage($uploadedFile);

            $productImage = new ProductImage($filename);
            $productImage->setPosition($command->position);
            $product->addImage($productImage);

            $this->entityManager->flush();

            $this->logger->info(
                'Product image processed successfully',
                [
                    'product_id' => $product->getId(),
                    'filename' => $filename,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'Failed to process product image',
                [
                    'product_id' => $command->productId,
                    'file' => $command->originalName,
                    'error' => $e->getMessage(),
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ]
            );

            throw $e;
        }
    }
}
