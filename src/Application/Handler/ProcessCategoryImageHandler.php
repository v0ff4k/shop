<?php

declare(strict_types=1);

namespace App\Application\Handler;

use App\Application\Command\ProcessCategoryImageCommand;
use App\Domain\Shop\Repository\CategoryRepositoryInterface;
use App\Domain\Shop\ValueObject\CategoryId;
use App\Infrastructure\Storage\FileUploadService;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Обработчик асинхронной загрузки изображения категории.
 */
final class ProcessCategoryImageHandler
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly FileUploadService $fileUploadService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ProcessCategoryImageCommand $command): void
    {
        $startedAt = microtime(true);

        $category = $this->categoryRepository->findById(CategoryId::fromInt((int) $command->categoryId));
        if ($category === null) {
            $this->logger->error('Category not found for image processing', [
                'category_id' => $command->categoryId,
            ]);

            return;
        }

        try {
            $uploadedFile = new UploadedFile(
                $command->filePath,
                $command->originalName,
                null,
                null,
                true
            );

            $this->fileUploadService->validate($uploadedFile);
            $category->setImage($this->fileUploadService->uploadCategoryImage($uploadedFile));
            $this->categoryRepository->save($category);

            $this->logger->info('Category image processed', [
                'category_id' => $category->getId(),
                'path' => $category->getImage(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to process category image', [
                'category_id' => $command->categoryId,
                'error' => $e->getMessage(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]);

            throw $e;
        }
    }
}
