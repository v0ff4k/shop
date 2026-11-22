<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Storage;

use App\Infrastructure\Helper\PathHelper;
use App\Infrastructure\Storage\FileUploadService;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class FileUploadServiceTest extends TestCase
{
    private FilesystemOperator&MockObject $productStorage;
    private FilesystemOperator&MockObject $categoryStorage;
    private FilesystemOperator&MockObject $userStorage;
    private PathHelper $pathHelper;
    private FileUploadService $service;

    protected function setUp(): void
    {
        $this->productStorage = $this->createMock(FilesystemOperator::class);
        $this->categoryStorage = $this->createMock(FilesystemOperator::class);
        $this->userStorage = $this->createMock(FilesystemOperator::class);
        $this->pathHelper = new PathHelper(
            uploadsPrefix: 'uploads',
            productPath: 'products',
            categoryPath: 'categories',
            userPath: 'users'
        );

        $this->service = new FileUploadService(
            $this->productStorage,
            $this->categoryStorage,
            $this->userStorage,
            $this->pathHelper
        );
    }

    public function testUploadProductImageCreatesHashPath(): void
    {
        // Создаем реальный временный файл для hash_file
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'test content');

        $file = $this->createMock(UploadedFile::class);
        $file->method('guessExtension')->willReturn('jpg');
        $file->method('getExtension')->willReturn('jpg');
        $file->method('getPathname')->willReturn($tmpFile);
        $file->method('getSize')->willReturn(1024);
        $file->method('getMimeType')->willReturn('image/jpeg');

        $this->productStorage
            ->method('directoryExists')
            ->willReturn(false);

        $this->productStorage
            ->expects($this->once())
            ->method('createDirectory');

        $this->productStorage
            ->expects($this->once())
            ->method('write');

        $result = $this->service->uploadProductImage($file);

        // Проверяем что путь содержит хэш-директории
        $this->assertMatchesRegularExpression('/^[a-f0-9]{2}\/[a-f0-9]{2}\/[a-f0-9]{64}\/[a-f0-9]{32}\.jpg$/', $result);

        unlink($tmpFile);
    }

    public function testUploadCategoryImage(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'category content');

        $file = $this->createMock(UploadedFile::class);
        $file->method('guessExtension')->willReturn('png');
        $file->method('getExtension')->willReturn('png');
        $file->method('getPathname')->willReturn($tmpFile);
        $file->method('getSize')->willReturn(2048);
        $file->method('getMimeType')->willReturn('image/png');

        $this->categoryStorage
            ->method('directoryExists')
            ->willReturn(false);

        $this->categoryStorage
            ->expects($this->once())
            ->method('createDirectory');

        $this->categoryStorage
            ->expects($this->once())
            ->method('write');

        $result = $this->service->uploadCategoryImage($file);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{2}\/[a-f0-9]{2}\/[a-f0-9]{64}\/[a-f0-9]{32}\.png$/', $result);

        unlink($tmpFile);
    }

    public function testUploadUserImageWithUserIdPrefix(): void
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'test');
        file_put_contents($tmpFile, 'user content');

        $file = $this->createMock(UploadedFile::class);
        $file->method('guessExtension')->willReturn('jpg');
        $file->method('getExtension')->willReturn('jpg');
        $file->method('getPathname')->willReturn($tmpFile);
        $file->method('getSize')->willReturn(512);
        $file->method('getMimeType')->willReturn('image/jpeg');

        $this->userStorage
            ->method('directoryExists')
            ->willReturn(false);

        $this->userStorage
            ->expects($this->once())
            ->method('createDirectory');

        $this->userStorage
            ->expects($this->once())
            ->method('write');

        $result = $this->service->uploadUserImage($file, 123);

        // Проверяем что имя файла содержит префикс user_123_
        $this->assertMatchesRegularExpression('/^user_123_[a-f0-9]{32}\.jpg$/', basename($result));

        unlink($tmpFile);
    }

    public function testValidateThrowsExceptionForLargeFile(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(10 * 1024 * 1024); // 10MB

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File size exceeds maximum allowed size');

        $this->service->validate($file);
    }

    public function testValidateThrowsExceptionForInvalidExtension(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(1024);
        $file->method('guessExtension')->willReturn('exe');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File extension is not allowed');

        $this->service->validate($file);
    }

    public function testValidateThrowsExceptionForInvalidMimeType(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getSize')->willReturn(1024);
        $file->method('guessExtension')->willReturn('jpg');
        $file->method('getMimeType')->willReturn('application/x-php');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('File is not a valid image');

        $this->service->validate($file);
    }

    public function testIsImageReturnsTrueForValidTypes(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMimeType')->willReturn('image/webp');

        $this->assertTrue($this->service->isImage($file));
    }

    public function testIsImageReturnsFalseForInvalidTypes(): void
    {
        $file = $this->createMock(UploadedFile::class);
        $file->method('getMimeType')->willReturn('application/pdf');

        $this->assertFalse($this->service->isImage($file));
    }

    public function testGetPublicUrl(): void
    {
        $path = 'ab/c1/abc123/file.jpg';
        $url = $this->service->getPublicUrl($path, 'products');

        $this->assertSame('/uploads/products/ab/c1/abc123/file.jpg', $url);
    }

    public function testGetPublicUrlWithUploadsPrefix(): void
    {
        $path = 'uploads/products/ab/c1/abc123/file.jpg';
        $url = $this->service->getPublicUrl($path, 'products');

        $this->assertSame('/uploads/products/ab/c1/abc123/file.jpg', $url);
    }

    public function testGetPublicUrlForCategory(): void
    {
        $path = 'ab/c1/abc123/category.jpg';
        $url = $this->service->getPublicUrl($path, 'categories');

        $this->assertSame('/uploads/categories/ab/c1/abc123/category.jpg', $url);
    }

    public function testDeleteFile(): void
    {
        $this->productStorage
            ->method('fileExists')
            ->willReturn(true);

        $this->productStorage
            ->expects($this->once())
            ->method('delete')
            ->with('ab/c1/abc123/file.jpg');

        $this->service->deleteFile('ab/c1/abc123/file.jpg', 'products');
    }
}
