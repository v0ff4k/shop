<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Миграция старых файлов в новую структуру с хэш-директориями
 * Обновляет пути в базе данных (category.image, product_image.path).
 */
#[AsCommand(
    name: 'app:migrate-uploads',
    description: 'Миграция файлов из старой структуры в новую (с хэш-директориями) + обновление БД'
)]
final class MigrateUploadsCommand extends Command
{
    // Маппинг старых файлов на новые хэш-пути
    private array $fileMapping = [];

    public function __construct(
        private readonly string $projectDir,
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Шаг 1: Миграция основных файлов (с хэшированием)
        $io->title('Шаг 1: Миграция основных файлов');

        $mainMappings = [
            'Products' => 'products',
            'Categories' => 'categories',
            'Users' => 'users',
        ];

        foreach ($mainMappings as $oldDir => $newDir) {
            $this->migrateFiles($io, $oldDir, $newDir, false);
        }

        // Шаг 2: Миграция thumb файлов (с тем же хэш-путем)
        $io->title('Шаг 2: Миграция thumb файлов');

        $thumbMappings = [
            'Products/thumb' => 'products/thumb',
            'Categories/thumb' => 'categories/thumb',
        ];

        foreach ($thumbMappings as $oldDir => $newDir) {
            $this->migrateFiles($io, $oldDir, $newDir, true);
        }

        // Шаг 3: Обновление БД
        $io->title('Шаг 3: Обновление путей в базе данных');
        $this->updateDatabase($io);

        $io->success('Миграция завершена!');

        return Command::SUCCESS;
    }

    /**
     * Миграция файлов из одной директории в другую.
     *
     * @param bool $isThumb Если true, использует тот же хэш-путь что и основной файл
     */
    private function migrateFiles(SymfonyStyle $io, string $oldDir, string $newDir, bool $isThumb): void
    {
        $oldPath = $this->projectDir.'/public/uploads/'.$oldDir;
        $newPath = $this->projectDir.'/public/uploads/'.$newDir;

        if (!is_dir($oldPath)) {
            $io->warning("Папка $oldDir не существует, пропускаем");

            return;
        }

        $io->section("Миграция $oldDir -> $newDir");

        if (!is_dir($newPath)) {
            if (!mkdir($newPath, 0777, true) && !is_dir($newPath)) {
                throw new \RuntimeException(\sprintf('Directory "%s" was not created', $newPath));
            }
        }

        $files = glob($oldPath.'/*.*');
        $migrated = 0;

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $filename = basename($file);

            if ($isThumb) {
                // Для thumb файлов используем тот же хэш-путь что и основной файл
                if (!isset($this->fileMapping[$filename])) {
                    $io->warning("  ⚠ $filename: не найден основной файл, пропускаем thumb");
                    continue;
                }

                // Берем хэш-путь из основного файла
                // $mainPath = categories/2d/de/2dde5dcb.../randomname.png
                $mainPath = $this->fileMapping[$filename];

                // Извлекаем хэш-путь (все после categories/ или products/)
                // Результат: 2d/de/2dde5dcb.../randomname.png
                if (preg_match('#(?:categories|products)/(.+)#', $mainPath, $matches)) {
                    $hashPathWithFilename = $matches[1];
                    $hashPath = \dirname($hashPathWithFilename); // 2d/de/2dde5dcb...
                    $mainFilename = basename($hashPathWithFilename); // randomname.png

                    $destDir = $newPath.'/'.$hashPath;

                    // Создаем директорию thumb/<hash_path>
                    if (!is_dir($destDir)) {
                        if (!mkdir($destDir, 0777, true) && !is_dir($destDir)) {
                            throw new \RuntimeException(\sprintf('Directory "%s" was not created', $destDir));
                        }
                    }

                    // Thumb файл имеет то же имя что и основной
                    $destPath = $destDir.'/'.$mainFilename;
                    $relativePath = $newDir.'/'.$hashPath.'/'.$mainFilename;
                } else {
                    $io->warning("  ⚠ $filename: не удалось извлечь хэш-путь");
                    continue;
                }
            } else {
                // Для основных файлов вычисляем хэш
                $contentHash = hash_file('sha256', $file);
                $hashPath = $this->buildHashPath($contentHash);
                $destDir = $newPath.'/'.$hashPath;

                if (!is_dir($destDir)) {
                    if (!mkdir($destDir, 0777, true) && !is_dir($destDir)) {
                        throw new \RuntimeException(\sprintf('Directory "%s" was not created', $destDir));
                    }
                }

                $extension = strtolower(pathinfo($filename, \PATHINFO_EXTENSION));
                $newFilename = bin2hex(random_bytes(16)).'.'.$extension;
                $destPath = $destDir.'/'.$newFilename;
                $relativePath = $newDir.'/'.$hashPath.'/'.$newFilename;

                // Сохраняем маппинг для thumb файлов
                $this->fileMapping[$filename] = $relativePath;
            }

            if (copy($file, $destPath)) {
                ++$migrated;
                $prefix = $isThumb ? '🖼' : '✓';
                $io->writeln("  $prefix $filename -> $relativePath");
            }
        }

        $type = $isThumb ? 'thumb' : 'основных';
        $io->success("Мигрировано $migrated $type файлов из $oldDir");
    }

    /**
     * Обновить пути в базе данных.
     */
    private function updateDatabase(SymfonyStyle $io): void
    {
        if (empty($this->fileMapping)) {
            $io->warning('Нет файлов для обновления в БД');

            return;
        }

        $updatedCategories = 0;
        $updatedImages = 0;

        // Обновляем category.image
        $categories = $this->connection->fetchAllAssociative('SELECT id, image FROM category');
        foreach ($categories as $category) {
            $oldImage = $category['image'];
            if (isset($this->fileMapping[$oldImage])) {
                $newPath = $this->fileMapping[$oldImage];
                $this->connection->update(
                    'category',
                    ['image' => $newPath],
                    ['id' => (int) $category['id']]
                );
                ++$updatedCategories;
                $io->writeln("  ✓ Category #{$category['id']}: $oldImage -> $newPath");
            }
        }

        // Обновляем product_image.path
        $images = $this->connection->fetchAllAssociative('SELECT id, path FROM product_image');
        foreach ($images as $image) {
            $oldPath = $image['path'];
            // Извлекаем имя файла из пути
            $filename = basename($oldPath);

            if (isset($this->fileMapping[$filename])) {
                $newPath = $this->fileMapping[$filename];
                $this->connection->update(
                    'product_image',
                    ['path' => $newPath],
                    ['id' => (int) $image['id']]
                );
                ++$updatedImages;
                $io->writeln("  ✓ ProductImage #{$image['id']}: $oldPath -> $newPath");
            }
        }

        $io->success("Обновлено: $updatedCategories категорий, $updatedImages изображений");
    }

    private function buildHashPath(string $contentHash): string
    {
        $parts = [];
        for ($i = 0; $i < 2; ++$i) {
            $offset = $i * 2;
            $parts[] = substr($contentHash, $offset, 2);
        }
        $parts[] = $contentHash;

        return implode('/', $parts);
    }
}
