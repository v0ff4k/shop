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
 * Команда для генерации thumb файлов из основных изображений.
 */
#[AsCommand(
    name: 'app:generate-thumbnails',
    description: 'Генерирует thumbnail изображения из основных файлов'
)]
final class GenerateThumbnailsCommand extends Command
{
    private const THUMB_WIDTH = 200;
    private const THUMB_HEIGHT = 200;

    public function __construct(
        private readonly string $projectDir,
        private readonly Connection $connection
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Генерация thumbnail изображений');

        // Проверяем наличие GD
        if (!\extension_loaded('gd')) {
            $io->error('Расширение GD не установлено');

            return Command::FAILURE;
        }

        $uploadsDir = $this->projectDir.'/public/uploads';
        $generated = 0;
        $errors = 0;

        // Получаем все основные изображения из БД
        $imagePaths = [];

        // category.image
        $categories = $this->connection->fetchAllAssociative('SELECT image FROM category WHERE image IS NOT NULL');
        foreach ($categories as $cat) {
            $imagePaths[] = $cat['image'];
        }

        // product_image.path
        $images = $this->connection->fetchAllAssociative('SELECT path FROM product_image WHERE path IS NOT NULL');
        foreach ($images as $img) {
            $imagePaths[] = $img['path'];
        }

        $io->info('Найдено '.\count($imagePaths).' изображений для генерации thumb');

        foreach ($imagePaths as $imagePath) {
            $fullPath = $uploadsDir.'/'.$imagePath;

            if (!file_exists($fullPath)) {
                $io->warning("Файл не найден: $imagePath");
                ++$errors;
                continue;
            }

            // Создаем thumb путь
            $thumbPath = $this->getThumbPath($imagePath);
            $thumbFullPath = $uploadsDir.'/'.$thumbPath;

            // Создаем директорию если не существует
            $thumbDir = \dirname($thumbFullPath);
            if (!is_dir($thumbDir)) {
                if (!mkdir($thumbDir, 0777, true) && !is_dir($thumbDir)) {
                    throw new \RuntimeException(\sprintf('Directory "%s" was not created', $thumbDir));
                }
            }

            // Генерируем thumbnail
            if ($this->createThumbnail($fullPath, $thumbFullPath)) {
                ++$generated;
                $io->writeln("  ✓ $imagePath -> $thumbPath");
            } else {
                ++$errors;
                $io->warning("  ✗ Ошибка создания thumb: $imagePath");
            }
        }

        $io->success("Сгенерировано $generated thumb, ошибок: $errors");

        return Command::SUCCESS;
    }

    /**
     * Получить thumb путь для изображения.
     */
    private function getThumbPath(string $imagePath): string
    {
        // Добавляем thumb после categories/ или products/
        return preg_replace(
            '#(categories|products)/(.+)#',
            '$1/thumb/$2',
            $imagePath
        );
    }

    /**
     * Создать thumbnail изображения.
     */
    private function createThumbnail(string $sourcePath, string $destPath): bool
    {
        // Определяем тип изображения
        $imageInfo = getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }

        $mimeType = $imageInfo['mime'];

        // Создаем источник
        switch ($mimeType) {
            case 'image/jpeg':
                $source = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $source = imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $source = imagecreatefromgif($sourcePath);
                break;
            case 'image/webp':
                $source = imagecreatefromwebp($sourcePath);
                break;
            default:
                return false;
        }

        if ($source === false) {
            return false;
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        // Вычисляем размеры с сохранением пропорций
        $ratio = min(self::THUMB_WIDTH / $sourceWidth, self::THUMB_HEIGHT / $sourceHeight);
        $thumbWidth = (int) ($sourceWidth * $ratio);
        $thumbHeight = (int) ($sourceHeight * $ratio);

        // Создаем thumbnail
        $thumb = imagecreatetruecolor($thumbWidth, $thumbHeight);

        // Сохраняем прозрачность для PNG
        if ($mimeType === 'image/png') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
        }

        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $sourceWidth, $sourceHeight);

        // Сохраняем thumbnail
        $result = false;
        switch ($mimeType) {
            case 'image/jpeg':
                $result = imagejpeg($thumb, $destPath, 85);
                break;
            case 'image/png':
                $result = imagepng($thumb, $destPath, 6);
                break;
            case 'image/gif':
                $result = imagegif($thumb, $destPath);
                break;
            case 'image/webp':
                $result = imagewebp($thumb, $destPath, 85);
                break;
        }

        imagedestroy($source);
        imagedestroy($thumb);

        return $result;
    }
}
