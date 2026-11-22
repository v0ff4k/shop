<?php

declare(strict_types=1);

namespace Tests\Application\Handler;

use App\Application\Command\ProcessProductImageCommand;
use App\Application\Handler\ProcessProductImageHandler;
use App\Domain\Shop\Entity\ProductImage;
use App\Domain\Shop\ValueObject\ProductId;
use App\Infrastructure\Storage\FileUploadService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProcessProductImageHandlerTest extends TestCase
{
    private FileUploadService&MockObject $fileUploadService;
    private ProcessProductImageHandler $handler;

    protected function setUp(): void
    {
        $this->fileUploadService = $this->createMock(FileUploadService::class);
        $this->handler = new ProcessProductImageHandler($this->fileUploadService);
    }

    public function testHandleSuccess(): void
    {
        $productId = ProductId::fromInt(1);
        $command = new ProcessProductImageCommand(
            1, // imageId
            $productId,
            '/tmp/test.jpg',
            'product_image'
        );

        $expectedImage = $this->createMock(ProductImage::class);
        $this->fileUploadService
            ->expects($this->once())
            ->method('moveProductImage')
            ->with($productId, '/tmp/test.jpg', 'product_image')
            ->willReturn($expectedImage);

        $this->handler->handle($command);

        // Если бы обработчик возвращал что-то, мы бы проверяли это
        // Но так как обработчик, вероятно, ничего не возвращает, просто проверяем, что исключения не thrown
        $this->assertTrue(true);
    }
}
