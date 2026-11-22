<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shop\Entity;

use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\Entity\Product;
use App\Domain\Shop\Entity\ProductImage;
use PHPUnit\Framework\TestCase;

/**
 * Тесты для доменного объекта ProductImage.
 */
final class ProductImageTest extends TestCase
{
    public function testCreateProductImage(): void
    {
        $product = new Product(
            'Test Product',
            '100.00',
            'Description',
            new Category('Test Category')
        );

        $image = new ProductImage('image1.jpg');
        $product->addImage($image);

        $this->assertSame($product, $image->getProduct());
        $this->assertSame('image1.jpg', $image->getPath());
        $this->assertSame(0, $image->getPosition());
        $this->assertNotNull($image->getUploadedAt());
    }

    public function testUpdateImageSortOrder(): void
    {
        $image = new ProductImage('image1.jpg');
        $image->setPosition(1);

        $this->assertSame(1, $image->getPosition());
    }

    public function testImagePaths(): void
    {
        $image = new ProductImage();

        $this->assertSame('', $image->getPath());

        $image->setPath('image1.jpg');

        $this->assertSame('image1.jpg', $image->getPath());
        $this->assertSame('image1.jpg', (string) $image);
    }
}
