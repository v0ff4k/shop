<?php

declare(strict_types=1);

namespace App\Tests\Application\Handler;

use App\Application\Command\ProcessCategoryImageCommand;
use App\Application\Handler\ProcessCategoryImageHandler;
use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\Repository\CategoryRepositoryInterface;
use App\Infrastructure\Helper\PathHelper;
use App\Infrastructure\Storage\FileUploadService;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ProcessCategoryImageHandlerTest extends TestCase
{
    public function testUploadsCategoryImageAndSavesCategory(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'category_');
        self::assertNotFalse($tmpFile);
        file_put_contents($tmpFile, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=',
            true
        ));

        $categoryStorage = $this->createMock(FilesystemOperator::class);
        $categoryStorage->method('directoryExists')->willReturn(false);
        $categoryStorage->expects(self::once())->method('createDirectory');
        $categoryStorage->expects(self::once())->method('write');

        $fileUploadService = new FileUploadService(
            $this->createMock(FilesystemOperator::class),
            $categoryStorage,
            $this->createMock(FilesystemOperator::class),
            new PathHelper()
        );

        $category = new Category('Catalog', 'catalog');
        $repository = $this->createMock(CategoryRepositoryInterface::class);
        $repository->method('findById')->willReturn($category);
        $repository->expects(self::once())->method('save')->with($category);

        $handler = new ProcessCategoryImageHandler(
            $repository,
            $fileUploadService,
            $this->createMock(LoggerInterface::class)
        );

        $handler(new ProcessCategoryImageCommand('1', $tmpFile, 'category.png'));

        self::assertNotNull($category->getImage());
        self::assertMatchesRegularExpression('/^[a-f0-9]{2}\/[a-f0-9]{2}\/[a-f0-9]{64}\/[a-f0-9]{32}\.png$/', $category->getImage());

        @unlink($tmpFile);
    }
}
