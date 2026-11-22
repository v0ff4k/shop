<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Infrastructure\Helper\PathHelper;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToWriteFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Сервис для загрузки и хранения файлов.
 *
 * Особенности:
 * - Файлы хранятся в подпапках по хэшу (избегает "тонущих" папок)
 * - Проверка дубликатов по content hash
 * - Защита от фишинга (уникальные имена для пользовательских файлов)
 */
final class FileUploadService
{
    // Глубина хэш-директорий (2 уровня по 2 символа) при изменении, ОБЯЗАТЕЛЬНО перенести старое!
    private const HASH_DIR_LEVELS = 2;
    private const HASH_DIR_LENGTH = 2;

    public function __construct(
        private readonly FilesystemOperator $productImagesStorage,
        private readonly FilesystemOperator $categoryImagesStorage,
        private readonly FilesystemOperator $userImagesStorage,
        private readonly PathHelper $pathHelper
    ) {
    }

    /**
     * Загрузить изображение продукта.
     *
     * @throws UnableToWriteFile
     *
     * @return string Путь к файлу относительно storage
     */
    public function uploadProductImage(UploadedFile $file): string
    {
        return $this->uploadFileWithHash($file, $this->productImagesStorage, $this->pathHelper->getProductPath());
    }

    /**
     * Загрузить изображение категории.
     *
     * @throws UnableToWriteFile
     *
     * @return string Путь к файлу относительно storage
     */
    public function uploadCategoryImage(UploadedFile $file): string
    {
        return $this->uploadFileWithHash($file, $this->categoryImagesStorage, $this->pathHelper->getCategoryPath());
    }

    /**
     * Загрузить изображение пользователя (с защитой от фишинга).
     *
     * @throws UnableToWriteFile
     *
     * @return string Путь к файлу относительно storage
     */
    public function uploadUserImage(UploadedFile $file, ?int $userId = null): string
    {
        $u = $this->pathHelper->getUserPath(); // users
        $prefix = $userId !== null
            ? (rtrim($u, 's')."_{$userId}_")
            : ''
        ;

        return $this->uploadFileWithHash($file, $this->userImagesStorage, $u, $prefix);
    }

    /**
     * Удалить файл.
     */
    public function deleteFile(string $path, string $storageType = 'products'): void
    {
        $storage = $this->getStorage($storageType);

        if ($storage->fileExists($path)) {
            $storage->delete($path);
        }
    }

    /**
     * Получить публичный URL файла.
     */
    public function getPublicUrl(string $path, string $storageType = 'products'): string
    {
        // Если путь уже полный (с uploads), используем normalize
        if ($this->pathHelper->hasUploadsPrefix($path)) {
            return $this->pathHelper->normalize($path);
        }

        // Иначе добавляем тип хранилища
        return match ($storageType) {
            'categories' => $this->pathHelper->categoryImageUrl($path),
            'users' => $this->pathHelper->normalize($this->pathHelper->getUserPath().'/'.ltrim($path, '/')),
            default => $this->pathHelper->productImageUrl($path),
        };
    }

    /**
     * Получить превью от пути файла(из БД).
     */
    public function getPublicThumbUrl(string $path, string $storageType = 'products'): string
    {
        return $this->pathHelper->thumbUrl($path);
    }

    /**
     * Валидировать файл.
     *
     * @param array<string> $allowedExtensions
     * @param int $maxSize Максимальный размер в байтах
     */
    public function validate(
        UploadedFile $file,
        array $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'],
        int $maxSize = 5242880
    ): bool {
        // Проверка размера
        if ($file->getSize() > $maxSize) {
            throw new \InvalidArgumentException('File size exceeds maximum allowed size');
        }

        // Проверка расширения
        $extension = strtolower($file->guessExtension() ?? '');
        if (!\in_array($extension, $allowedExtensions, true)) {
            throw new \InvalidArgumentException('File extension is not allowed');
        }

        // Проверка MIME типа
        if (!$this->isImage($file)) {
            throw new \InvalidArgumentException('File is not a valid image');
        }

        return true;
    }

    /**
     * Проверить тип файла.
     */
    public function isImage(UploadedFile $file): bool
    {
        $allowedMimeTypes = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'image/svg+xml',
        ];

        return \in_array($file->getMimeType(), $allowedMimeTypes, true);
    }

    /**
     * Загрузка файла с хэш-директориями и проверкой дубликатов.
     */
    private function uploadFileWithHash(
        UploadedFile $file,
        FilesystemOperator $storage,
        string $type,
        string $prefix = ''
    ): string {
        // Валидация
        $this->validate($file);

        // Вычисляем content hash для проверки дубликатов
        $contentHash = hash_file('sha256', $file->getPathname());
        if ($contentHash === false) {
            throw new UnableToWriteFile("Unable to calculate file hash: {$file->getPathname()}");
        }

        // Проверяем дубликат
        $existingPath = $this->findDuplicate($storage, $contentHash, $type);
        if ($existingPath !== null) {
            return $existingPath;
        }

        // Генерируем уникальное имя файла
        $extension = strtolower($file->guessExtension() ?? $file->getExtension());
        $uniqueName = $prefix.bin2hex(random_bytes(16)).'.'.$extension;

        // Создаем хэш-путь для распределения файлов
        $hashPath = $this->buildHashPath($contentHash);
        $filePath = $hashPath.'/'.$uniqueName;

        // Создаем директорию если не существует
        $directory = \dirname($filePath);
        if (!$storage->directoryExists($directory)) {
            $storage->createDirectory($directory);
        }

        // Читаем и записываем файл
        $fileContent = file_get_contents($file->getPathname());
        if ($fileContent === false) {
            throw new UnableToWriteFile("Unable to read file: {$file->getPathname()}");
        }

        $storage->write($filePath, $fileContent);

        return $filePath;
    }

    /**
     * Построить хэш-путь для распределения файлов
     * Пример: abc123... -> ab/c1/abc123def456...
     */
    private function buildHashPath(string $contentHash): string
    {
        $parts = [];
        for ($i = 0; $i < self::HASH_DIR_LEVELS; ++$i) {
            $offset = $i * self::HASH_DIR_LENGTH;
            $parts[] = substr($contentHash, $offset, self::HASH_DIR_LENGTH);
        }
        $parts[] = $contentHash;

        return implode('/', $parts);
    }

    /**
     * Поиск дубликата файла по content hash
     * Возвращает путь к существующему файлу или null.
     */
    private function findDuplicate(
        FilesystemOperator $storage,
        string $contentHash,
        string $type
    ): ?string {
        $hashPath = $this->buildHashPath($contentHash);

        // Проверяем существует ли файл с таким хэшем
        $listing = $storage->listContents($hashPath, false);

        foreach ($listing as $item) {
            // Если файл с таким хэшем уже существует - возвращаем его путь
            if (str_contains($item->path(), $contentHash)) {
                return $item->path();
            }
        }

        return null;
    }

    /**
     * Получить хранилище по типу.
     */
    private function getStorage(string $type): FilesystemOperator
    {
        return match ($type) {
            $this->pathHelper->getCategoryPath() => $this->categoryImagesStorage,
            $this->pathHelper->getUserPath() => $this->userImagesStorage,
            default => $this->productImagesStorage,
        };
    }
}
