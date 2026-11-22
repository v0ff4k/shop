<?php

declare(strict_types=1);

namespace App\Command;

use App\Infrastructure\Helper\PathHelper;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

/**
 * Команда для очистки orphan файлов (которых нет в БД).
 */
#[AsCommand(
    name: 'app:cleanup-orphan-files',
    description: 'Удаляет файлы из uploads/, которых нет в базе данных'
)]
final class CleanupOrphanFilesCommand extends Command
{
    public function __construct(
        private readonly string $projectDir,
        private readonly Connection $connection,
        private readonly PathHelper $pathHelper
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'dry-run',
            null,
            InputOption::VALUE_NONE,
            'Только показать файлы для удаления, не удалять'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $io->title('РЕЖИМ ПРОСМОТРА (dry-run)');
        } else {
            $io->title('ОЧИСТКА ORPHAN ФАЙЛОВ');
        }

        $uploadsDir = $this->projectDir.'/public/uploads';

        if (!is_dir($uploadsDir)) {
            $io->error('Директория uploads не существует');

            return Command::FAILURE;
        }

        // Получаем все пути из БД
        $dbPaths = $this->getDatabasePaths();
        $io->info('Найдено '.\count($dbPaths).' записей в БД');

        $finder = new Finder();
        $finder->files()->in($uploadsDir)->name('*.jpg')->name('*.jpeg')->name('*.png')->name('*.gif')->name('*.webp');

        $orphanFiles = [];
        $validFiles = 0;

        foreach ($finder as $file) {
            $relativePath = str_replace($uploadsDir.'/', '', $file->getPathname());

            if ($this->isFileInDatabase($relativePath, $dbPaths)) {
                ++$validFiles;
            } else {
                $orphanFiles[] = $relativePath;
            }
        }

        $io->success("Найдено $validFiles валидных файлов");

        if (empty($orphanFiles)) {
            $io->success('Orphan файлов не найдено!');

            return Command::SUCCESS;
        }

        $io->warning('Найдено '.\count($orphanFiles).' orphan файлов');

        foreach ($orphanFiles as $orphanFile) {
            if ($dryRun) {
                $io->writeln("  📄 $orphanFile");
            } else {
                $fullPath = $uploadsDir.'/'.$orphanFile;
                if (unlink($fullPath)) {
                    $io->writeln("  ✓ Удален: $orphanFile");
                } else {
                    $io->writeln("  ✗ Ошибка удаления: $orphanFile");
                }
            }
        }

        // Очистка пустых директорий
        if (!$dryRun) {
            $this->cleanupEmptyDirectories($uploadsDir, $io);
        }

        if ($dryRun) {
            $io->note('Это был dry-run. Для реального удаления запустите без --dry-run');
        } else {
            $io->success('Очистка завершена!');
        }

        return Command::SUCCESS;
    }

    /**
     * Получить все пути к файлам из БД.
     */
    private function getDatabasePaths(): array
    {
        $paths = [];

        // category.image
        $categories = $this->connection->fetchAllAssociative('SELECT image FROM category WHERE image IS NOT NULL');
        foreach ($categories as $cat) {
            $paths[] = $cat['image'];
            // Также добавляем thumb путь
            $paths[] = $this->pathHelper->addThumbToPath($cat['image']);
        }

        // product_image.path
        $images = $this->connection->fetchAllAssociative('SELECT path FROM product_image WHERE path IS NOT NULL');
        foreach ($images as $img) {
            $paths[] = $img['path'];
            // Также добавляем thumb путь
            $paths[] = $this->pathHelper->addThumbToPath($img['path']);
        }

        return $paths;
    }

    /**
     * Проверить есть ли файл в БД.
     */
    private function isFileInDatabase(string $filePath, array $dbPaths): bool
    {
        // Убираем ведущий / если есть
        $filePath = ltrim($filePath, '/');

        return \in_array($filePath, $dbPaths, true);
    }

    /**
     * Очистить пустые директории.
     */
    private function cleanupEmptyDirectories(string $dir, SymfonyStyle $io): void
    {
        $finder = new Finder();
        $finder->directories()->in($dir)->depth('>= 1');

        // Сортируем по глубине (сначала самые вложенные)
        $directories = iterator_to_array($finder);
        usort($directories, static function ($a, $b) {
            return substr_count($b->getPathname(), '/') - substr_count($a->getPathname(), '/');
        });

        foreach ($directories as $directory) {
            $path = $directory->getPathname();
            if (is_dir($path) && \count(scandir($path)) === 2) { // Только . и ..
                rmdir($path);
                $io->writeln('  📁 Удалена пустая папка: '.str_replace($dir.'/', '', $path));
            }
        }
    }
}
