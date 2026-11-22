<?php

declare(strict_types=1);

namespace App\Tests\Domain\Shop\Entity;

use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\Entity\Product;
use App\Domain\Shop\Entity\ProductImage;
use PHPUnit\Framework\TestCase;

/**
 * Тесты для доменной сущности Product.
 */
final class ProductTest extends TestCase
{
    private Category $category;

    protected function setUp(): void
    {
        $this->category = new Category();
        $this->category->setTitle('Test Category');
    }

    public function testCreateProduct(): void
    {
        $product = new Product(
            'Test Product',
            '99.99',
            'Product description',
            $this->category
        );

        $this->assertSame('Test Product', $product->getTitle());
        $this->assertSame('99.99', $product->getPrice());
        $this->assertSame('Product description', $product->getDescription());
        $this->assertSame($this->category, $product->getCategory());
        $this->assertSame(0, $product->getAvailable());
        $this->assertNotNull($product->getAdded());
    }

    public function testProductSlugIsGenerated(): void
    {
        $product = new Product(
            'My Awesome Product',
            '100.00',
            'Description',
            $this->category
        );

        $this->assertSame('my-awesome-product', $product->getSlug());
    }

    public function testSetTitleRegeneratesSlug(): void
    {
        $product = new Product(
            'Original Title',
            '100.00',
            'Description',
            $this->category
        );

        $this->assertSame('original-title', $product->getSlug());

        $product->setTitle('New Title');

        $this->assertSame('new-title', $product->getSlug());
        $this->assertSame('New Title', $product->getTitle());
    }

    public function testSetPrice(): void
    {
        $product = new Product(
            'Product',
            '100.00',
            'Description',
            $this->category
        );

        $product->setPrice('199.99');

        $this->assertSame('199.99', $product->getPrice());
    }

    public function testSetDescription(): void
    {
        $product = new Product(
            'Product',
            '100.00',
            'Old description',
            $this->category
        );

        $product->setDescription('New description');

        $this->assertSame('New description', $product->getDescription());
    }

    public function testSetAvailable(): void
    {
        $product = new Product(
            'Product',
            '100.00',
            'Description',
            $this->category
        );

        $product->setAvailable(50);

        $this->assertSame(50, $product->getAvailable());
        $this->assertTrue($product->isAvailable());
    }

    public function testIsAvailableWhenZero(): void
    {
        $product = new Product(
            'Product',
            '100.00',
            'Description',
            $this->category
        );

        $this->assertFalse($product->isAvailable());
    }

    public function testIncrementViewed(): void
    {
        $product = new Product(
            'Product',
            '100.00',
            'Description',
            $this->category
        );

        $initialViewed = $product->getViewed();

        $product->incrementViewed();

        $this->assertSame($initialViewed + 1, $product->getViewed());
        $this->assertNotNull($product->getLastViewed());
    }

    public function testGetPriceVO(): void
    {
        $product = new Product(
            'Product',
            '99.99',
            'Description',
            $this->category
        );

        $money = $product->getPriceVO();

        $this->assertSame(9999, $money->amount());
        $this->assertSame(99.99, $money->amountFloat());
    }

    public function testSetImagesSynchronizesCollection(): void
    {
        $product = new Product('Product', '100.00', 'Description', $this->category);
        $first = new ProductImage('first.jpg');
        $second = new ProductImage('second.jpg');
        $third = new ProductImage('third.jpg');

        $product->addImage($first);
        $product->addImage($second);

        $product->setImages([$second, $third]);

        $paths = array_map(
            static fn (ProductImage $image): string => $image->getPath(),
            $product->getImages()
        );

        self::assertSame(['second.jpg', 'third.jpg'], array_values($paths));
        self::assertSame($product, $second->getProduct());
        self::assertSame($product, $third->getProduct());
        self::assertNull($first->getProduct());
    }

    public function testProductImageHasSafeDefaultPath(): void
    {
        self::assertSame('', (new ProductImage())->getPath());
    }

    public function testPreUpdateDoesNotSetEditedWhenOnlyPriceChanges(): void
    {
        $product = new Product('Product', '100.00', 'Description', $this->category);

        $product->setPrice('120.00');
        $product->setAvailable(5);
        $product->onPreUpdate();

        $this->assertNull($product->getEdited());
    }

    public function testPreUpdateDoesNotSetEditedWhenTitleAndDescriptionAreUnchanged(): void
    {
        $product = new Product('Product', '100.00', 'Description', $this->category);

        $product->setTitle('Product');
        $product->setDescription('Description');
        $product->onPreUpdate();

        $this->assertNull($product->getEdited());
    }

    public function testPreUpdateSetsEditedWhenTitleChanges(): void
    {
        $product = new Product('Product', '100.00', 'Description', $this->category);

        $product->setTitle('Updated Product');
        $product->onPreUpdate();

        $this->assertNotNull($product->getEdited());
    }

    public function testPreUpdateSetsEditedWhenDescriptionChanges(): void
    {
        $product = new Product('Product', '100.00', 'Description', $this->category);

        $product->setDescription('Updated description');
        $product->onPreUpdate();

        $this->assertNotNull($product->getEdited());
    }

    public function testToString(): void
    {
        $product = new Product(
            'Test Product Name',
            '100.00',
            'Description',
            $this->category
        );

        $this->assertSame('Test Product Name', (string) $product);
    }
}
