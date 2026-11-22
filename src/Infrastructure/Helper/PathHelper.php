<?php

declare(strict_types=1);

namespace App\Infrastructure\Helper;

/**
 * Хелпер для обработки путей к файлам
 *
 * Отвечает за:
 * - Нормализацию путей к загруженным файлам
 * - Добавление/удаление префикса uploads/
 * - Обработку путей к превью (thumb)
 *
 * Пути конфигурируются через параметры Symfony:
 * - product.images.path
 * - category.images.path
 * - uploads.prefix
 */
final class PathHelper
{
    /** @var string 'uploads/' */
    private string $uploadsPrefix;
    /** @var string 'products' */
    private string $productPath;
    /** @var string 'categories' */
    private string $categoryPath;
    /** @var string 'users' */
    private string $userPath;
    /** @var string 'thumb/' */
    private string $thumbPrefix;

    public function __construct(
        string $uploadsPrefix = 'uploads/',
        string $productPath = 'products',
        string $categoryPath = 'categories',
        string $userPath = 'users',
        string $thumbPrefix = 'thumb/'
    ) {
        $this->uploadsPrefix = rtrim($uploadsPrefix, '/');
        $this->productPath = $productPath;
        $this->categoryPath = $categoryPath;
        $this->userPath = $userPath;
        $this->thumbPrefix = rtrim($thumbPrefix, '/');
    }

    /**
     * Получить префикс uploads.
     */
    public function getUploadsPrefix(): string
    {
        return $this->uploadsPrefix;
    }

    /**
     * Получить путь к продуктам
     */
    public function getProductPath(): string
    {
        return $this->productPath;
    }

    /**
     * Получить путь к категориям
     */
    public function getCategoryPath(): string
    {
        return $this->categoryPath;
    }

    /**
     * Получить путь к пользователям
     */
    public function getUserPath(): string
    {
        return $this->userPath;
    }

    /**
     * Нормализует путь к файлу.
     *
     * @param string $path Путь к файлу (с префиксом или без)
     *
     * @return string Нормализованный путь начинающийся с /
     */
    public function normalize(string $path): string
    {
        if ($this->hasUploadsPrefix($path)) {
            return '/'.ltrim($path, '/');
        }

        return '/'.$this->uploadsPrefix.'/'.ltrim($path, '/');
    }

    /**
     * Проверяет имеет ли путь префикс uploads/.
     */
    public function hasUploadsPrefix(string $path): bool
    {
        return str_starts_with($path, $this->uploadsPrefix.'/')
            || str_starts_with($path, '/'.$this->uploadsPrefix.'/');
    }

    /**
     * Добавляет thumb к пути если его нет
     *
     * Примеры:
     * - categories/ab/cd/abcd1234/file.jpg -> categories/thumb/ab/cd/abcd1234/file.jpg
     * - uploads/categories/ab/cd/abcd1234/file.jpg -> uploads/categories/thumb/ab/cd/abcd1234/file.jpg
     * - categories/thumb/ab/cd/abcd1234/file.jpg -> categories/thumb/ab/cd/abcd1234/file.jpg (без изменений)
     * - users/ab/cd/abcd1234/file.jpg -> users/thumb/ab/cd/abcd1234/file.jpg
     */
    public function addThumbToPath(string $path): string
    {
        // Если уже thumb путь, возвращаем как есть
        if ($this->isThumbPath($path)) {
            return $path;
        }

        // Паттерн: (uploads/)?(categories|products|users)/<hash_path>/filename
        // Замена: $1$2/thumb/<hash_path>/filename
        $types = implode('|', [$this->categoryPath, $this->productPath, $this->userPath]);
        $pattern = '#('.$this->uploadsPrefix.')?('.$types.')/([^/]+/.+)$#';
        $replacement = '$1$2/'.$this->thumbPrefix.'/$3';

        return preg_replace($pattern, $replacement, $path);
    }

    /**
     * Создает URL для thumb файла.
     *
     * @param string $path Путь к файлу
     *
     * @return string URL к thumb файлу
     */
    public function thumbUrl(string $path): string
    {
        $path = $this->addThumbToPath($path);

        return $this->normalize($path);
    }

    /**
     * Извлекает имя файла из пути.
     */
    public function extractFilename(string $path): string
    {
        return basename($path);
    }

    /**
     * Извлекает директорию из пути (без имени файла).
     */
    public function extractDirectory(string $path): string
    {
        return \dirname($path);
    }

    /**
     * Проверяет является ли путь thumb путем
     */
    public function isThumbPath(string $path): bool
    {
        return str_contains($path, $this->thumbPrefix.'/');
    }

    /**
     * Получить полный путь к изображению продукта.
     */
    public function productImageUrl(string $imagePath): string
    {
        return $this->normalize($this->productPath.'/'.ltrim($imagePath, '/'));
    }

    /**
     * Получить полный путь к thumb изображения продукта.
     */
    public function productThumbUrl(string $imagePath): string
    {
        return $this->thumbUrl($this->productPath.'/'.ltrim($imagePath, '/'));
    }

    /**
     * Получить полный путь к изображению категории.
     */
    public function categoryImageUrl(string $imagePath): string
    {
        return $this->normalize($this->categoryPath.'/'.ltrim($imagePath, '/'));
    }

    /**
     * Получить полный путь к thumb изображения категории.
     */
    public function categoryThumbUrl(string $imagePath): string
    {
        return $this->thumbUrl($this->categoryPath.'/'.ltrim($imagePath, '/'));
    }
}
