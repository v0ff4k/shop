<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Helper;

use App\Infrastructure\Helper\PathHelper;
use PHPUnit\Framework\TestCase;

final class PathHelperTest extends TestCase
{
    private PathHelper $pathHelper;

    protected function setUp(): void
    {
        $this->pathHelper = new PathHelper(
            uploadsPrefix: 'uploads',
            productPath: 'products',
            categoryPath: 'categories',
            userPath: 'users'
        );
    }

    public static function normalizeProvider(): iterable
    {
        yield 'путь без префикса' => [
            'categories/file.jpg',
            '/uploads/categories/file.jpg',
        ];

        yield 'путь с uploads/' => [
            'uploads/categories/file.jpg',
            '/uploads/categories/file.jpg',
        ];

        yield 'путь с /uploads/' => [
            '/uploads/categories/file.jpg',
            '/uploads/categories/file.jpg',
        ];

        yield 'products путь' => [
            'products/ab/cd/abcd1234/file.png',
            '/uploads/products/ab/cd/abcd1234/file.png',
        ];

        yield 'thumb путь' => [
            'products/thumb/ab/cd/abcd1234/file.png',
            '/uploads/products/thumb/ab/cd/abcd1234/file.png',
        ];
    }

    public static function hasUploadsPrefixProvider(): iterable
    {
        yield 'с uploads/' => ['uploads/file.jpg', true];
        yield 'с /uploads/' => ['/uploads/file.jpg', true];
        yield 'без префикса' => ['categories/file.jpg', false];
        yield 'другой префикс' => ['/build/app.js', false];
        yield 'пустая строка' => ['', false];
    }

    public static function addThumbToPathProvider(): iterable
    {
        yield 'categories без thumb' => [
            'categories/ab/cd/abcd1234/file.jpg',
            'categories/thumb/ab/cd/abcd1234/file.jpg',
        ];

        yield 'products без thumb' => [
            'products/ab/cd/abcd1234/file.jpg',
            'products/thumb/ab/cd/abcd1234/file.jpg',
        ];

        yield 'categories с thumb' => [
            'categories/thumb/ab/cd/abcd1234/file.jpg',
            'categories/thumb/ab/cd/abcd1234/file.jpg',
        ];

        yield 'products с thumb' => [
            'products/thumb/ab/cd/abcd1234/file.jpg',
            'products/thumb/ab/cd/abcd1234/file.jpg',
        ];

        yield 'с uploads/ префиксом' => [
            'uploads/categories/ab/cd/abcd1234/file.jpg',
            'uploads/categories/thumb/ab/cd/abcd1234/file.jpg',
        ];

        yield 'с /uploads/ префиксом' => [
            '/uploads/categories/ab/cd/abcd1234/file.jpg',
            '/uploads/categories/thumb/ab/cd/abcd1234/file.jpg',
        ];

        yield 'users без thumb' => [
            'users/ab/cd/abcd1234/file.jpg',
            'users/thumb/ab/cd/abcd1234/file.jpg',
        ];
    }

    public static function thumbUrlProvider(): iterable
    {
        yield 'categories без thumb и без uploads' => [
            'categories/ab/cd/abcd1234/file.jpg',
            '/uploads/categories/thumb/ab/cd/abcd1234/file.jpg',
        ];

        yield 'products с thumb и uploads' => [
            'uploads/products/thumb/ab/cd/abcd1234/file.jpg',
            '/uploads/products/thumb/ab/cd/abcd1234/file.jpg',
        ];

        yield 'products без thumb с uploads' => [
            'uploads/products/ab/cd/abcd1234/file.jpg',
            '/uploads/products/thumb/ab/cd/abcd1234/file.jpg',
        ];
    }

    public static function extractFilenameProvider(): iterable
    {
        yield 'простой путь' => ['file.jpg', 'file.jpg'];
        yield 'с директорией' => ['path/to/file.jpg', 'file.jpg'];
        yield 'с uploads/' => ['uploads/categories/file.jpg', 'file.jpg'];
        yield 'с хэш путем' => ['ab/cd/abcd1234/file.png', 'file.png'];
    }

    public static function extractDirectoryProvider(): iterable
    {
        yield 'простой путь' => ['path/to/file.jpg', 'path/to'];
        yield 'только файл' => ['file.jpg', '.'];
        yield 'с uploads/' => ['uploads/categories/file.jpg', 'uploads/categories'];
    }

    public static function isThumbPathProvider(): iterable
    {
        yield 'с thumb в середине' => ['products/thumb/ab/cd/file.jpg', true];
        yield 'с thumb в начале' => ['thumb/ab/cd/file.jpg', true];
        yield 'без thumb' => ['products/ab/cd/file.jpg', false];
        yield 'thumb в имени файла' => ['products/ab/cd/thumb_file.jpg', false];
    }

    /**
     * @dataProvider normalizeProvider
     */
    public function testNormalize(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->pathHelper->normalize($input));
    }

    /**
     * @dataProvider hasUploadsPrefixProvider
     */
    public function testHasUploadsPrefix(string $input, bool $expected): void
    {
        $this->assertSame($expected, $this->pathHelper->hasUploadsPrefix($input));
    }

    /**
     * @dataProvider addThumbToPathProvider
     */
    public function testAddThumbToPath(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->pathHelper->addThumbToPath($input));
    }

    /**
     * @dataProvider thumbUrlProvider
     */
    public function testThumbUrl(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->pathHelper->thumbUrl($input));
    }

    /**
     * @dataProvider extractFilenameProvider
     */
    public function testExtractFilename(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->pathHelper->extractFilename($input));
    }

    /**
     * @dataProvider extractDirectoryProvider
     */
    public function testExtractDirectory(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->pathHelper->extractDirectory($input));
    }

    /**
     * @dataProvider isThumbPathProvider
     */
    public function testIsThumbPath(string $input, bool $expected): void
    {
        $this->assertSame($expected, $this->pathHelper->isThumbPath($input));
    }

    public function testGetProductPath(): void
    {
        $this->assertSame('products', $this->pathHelper->getProductPath());
    }

    public function testGetCategoryPath(): void
    {
        $this->assertSame('categories', $this->pathHelper->getCategoryPath());
    }

    public function testGetUserPath(): void
    {
        $this->assertSame('users', $this->pathHelper->getUserPath());
    }

    public function testGetUploadsPrefix(): void
    {
        $this->assertSame('uploads', $this->pathHelper->getUploadsPrefix());
    }

    public function testProductImageUrl(): void
    {
        $this->assertSame('/uploads/products/ab/cd/file.jpg', $this->pathHelper->productImageUrl('ab/cd/file.jpg'));
    }

    public function testProductThumbUrl(): void
    {
        $this->assertSame('/uploads/products/thumb/ab/cd/file.jpg', $this->pathHelper->productThumbUrl('ab/cd/file.jpg'));
    }

    public function testCategoryImageUrl(): void
    {
        $this->assertSame('/uploads/categories/ab/cd/file.jpg', $this->pathHelper->categoryImageUrl('ab/cd/file.jpg'));
    }

    public function testCategoryThumbUrl(): void
    {
        $this->assertSame('/uploads/categories/thumb/ab/cd/file.jpg', $this->pathHelper->categoryThumbUrl('ab/cd/file.jpg'));
    }
}
