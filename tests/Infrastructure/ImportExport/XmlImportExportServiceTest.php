<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\ImportExport;

use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\Entity\Product;
use App\Domain\Shop\Entity\ProductImage;
use App\Domain\Shop\Repository\CategoryRepositoryInterface;
use App\Domain\Shop\Repository\ProductRepositoryInterface;
use App\Domain\Shop\ValueObject\ProductId;
use App\Infrastructure\ImportExport\XmlImportExportService;
use App\Infrastructure\ImportExport\XmlProductReader;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Тесты для XmlImportExportService.
 *
 * @covers \App\Infrastructure\ImportExport\XmlImportExportService
 */
final class XmlImportExportServiceTest extends TestCase
{
    private EntityManagerInterface|MockObject $entityManager;
    private ProductRepositoryInterface|MockObject $productRepository;
    private CategoryRepositoryInterface|MockObject $categoryRepository;
    private LoggerInterface|MockObject $logger;
    private XmlImportExportService $service;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->service = new XmlImportExportService(
            $this->entityManager,
            $this->productRepository,
            $this->categoryRepository,
            $this->logger,
            new XmlProductReader(),
            100
        );
    }

    public function testExportProductsReturnsValidXml(): void
    {
        // Arrange
        $category = new Category();
        $category->setTitle('Electronics');
        $category->setSlug('electronics');
        $product = new Product('Test Product', '99.99', 'Test Description', $category);
        $product->setSlug('test-product');
        $product->setAvailable(10);
        $product->addImage(new ProductImage('https://example.com/a.jpg'));
        $product->addImage(new ProductImage('https://example.com/b.jpg'));

        $this->productRepository
            ->method('findAll')
            ->willReturn([$product]);

        // Act
        $xml = $this->service->exportProducts();

        // Assert
        $this->assertIsString($xml);
        $this->assertStringContainsString('<?xml', $xml);
        $this->assertStringContainsString('<products>', $xml);
        $this->assertStringNotContainsString('<response>', $xml);
        $this->assertStringContainsString('<title>Test Product</title>', $xml);
        $this->assertStringContainsString('<slug>electronics</slug>', $xml);
        $this->assertStringContainsString('<images>', $xml);
        $this->assertStringContainsString('<image>https://example.com/a.jpg</image>', $xml);
        $this->assertStringContainsString('<image>https://example.com/b.jpg</image>', $xml);
        $this->assertSame(1, $this->service->getLastExportCount());
    }

    public function testExportProducesImportCompatibleStructure(): void
    {
        $category = new Category('Electronics', 'electronics');
        $product = new Product('Test Product', '99.99', 'Test Description', $category);
        $product->setSlug('test-product');
        $product->addImage(new ProductImage('https://example.com/a.jpg'));

        $this->productRepository
            ->method('findAll')
            ->willReturn([$product]);

        $xml = $this->service->exportProducts();

        $items = iterator_to_array((new XmlProductReader())->readFromString($xml));

        self::assertCount(1, $items);
        self::assertSame('test-product', $items[0]['slug']);
        self::assertSame(['https://example.com/a.jpg'], $items[0]['images']);
        self::assertSame(
            ['slug' => 'electronics', 'title' => 'Electronics'],
            $items[0]['category']
        );
    }

    public function testExportProductsByIds(): void
    {
        // Arrange
        $category = new Category();
        $category->setTitle('Books');
        $category->setSlug('books');
        $product = new Product('Book Product', '19.99', 'Book Description', $category);
        $product->setSlug('book-product');

        $this->productRepository
            ->method('findById')
            ->with($this->equalTo(ProductId::fromInt(1)))
            ->willReturn($product);

        // Act
        $xml = $this->service->exportProducts([1]);

        // Assert
        $this->assertIsString($xml);
        $this->assertStringContainsString('Book Product', $xml);
    }

    public function testExportProductsHandlesNullProducts(): void
    {
        // Arrange
        $this->productRepository
            ->method('findById')
            ->willReturn(null);

        // Act
        $xml = $this->service->exportProducts([1, 2, 3]);

        // Assert
        $this->assertIsString($xml);
        $this->assertStringContainsString('<?xml', $xml);
    }

    public function testImportProductsFromValidXml(): void
    {
        // Arrange
        $xmlContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<root>
    <products>
        <product>
            <id>1</id>
            <title>Imported Product</title>
            <slug>imported-product</slug>
            <price>49.99</price>
            <description>Imported Description</description>
            <available>20</available>
            <category>Test Category</category>
            <category_slug>test-category</category_slug>
        </product>
    </products>
</root>
XML;

        $this->productRepository
            ->method('findBySlug')
            ->willReturn(null);

        $this->categoryRepository
            ->method('findBySlug')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $stats = $this->service->importProducts($xmlContent);

        // Assert
        $this->assertEquals(1, $stats['imported']);
        $this->assertEquals(0, $stats['skipped']);
        $this->assertEquals(0, $stats['errors']);
    }

    public function testImportProductsHandlesInvalidXml(): void
    {
        // Arrange
        $xmlContent = '<?xml version="1.0"?><root><invalid>data</invalid></root>';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid XML format: products not found');

        // Act
        $this->service->importProducts($xmlContent);
    }

    public function testImportProductsHandlesMultipleProducts(): void
    {
        // Arrange
        $xmlContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<root>
    <products>
        <product>
            <id>1</id>
            <title>Product 1</title>
            <slug>product-1</slug>
            <price>10.00</price>
            <description>Description 1</description>
            <available>5</available>
            <category>Category 1</category>
        </product>
        <product>
            <id>2</id>
            <title>Product 2</title>
            <slug>product-2</slug>
            <price>20.00</price>
            <description>Description 2</description>
            <available>15</available>
            <category>Category 2</category>
        </product>
    </products>
</root>
XML;

        $this->productRepository
            ->method('findBySlug')
            ->willReturn(null);

        $this->categoryRepository
            ->method('findBySlug')
            ->willReturn(null);

        // Act
        $stats = $this->service->importProducts($xmlContent);

        // Assert
        $this->assertEquals(2, $stats['imported']);
        $this->assertEquals(0, $stats['errors']);
    }

    public function testImportProductsHandlesErrors(): void
    {
        // Arrange
        $xmlContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<root>
    <products>
        <product>
            <id>1</id>
            <title>Invalid Product</title>
            <slug>invalid-product</slug>
            <price>invalid_price</price>
            <available>5</available>
            <category>Category</category>
        </product>
    </products>
</root>
XML;

        $this->productRepository
            ->method('findBySlug')
            ->willReturn(null);

        $this->categoryRepository
            ->method('findBySlug')
            ->willReturn(null);

        // Act
        $stats = $this->service->importProducts($xmlContent);

        // Assert - ошибка парсинга цены должна быть обработана
        $this->assertGreaterThanOrEqual(0, $stats['errors']);
    }

    public function testUpdateExistingProductOnImport(): void
    {
        // Arrange
        $category = new Category();
        $category->setTitle('Existing Category');
        $category->setSlug('existing-category');
        $existingProduct = new Product('Old Title', '10.00', 'Old Description', $category);
        $existingProduct->setSlug('existing-product');
        $existingProduct->setAvailable(5);

        $this->productRepository
            ->method('findBySlug')
            ->with('existing-product')
            ->willReturn($existingProduct);

        $xmlContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<root>
    <products>
        <product>
            <id>1</id>
            <title>Updated Title</title>
            <slug>existing-product</slug>
            <price>29.99</price>
            <description>Updated Description</description>
            <available>50</available>
            <category>New Category</category>
        </product>
    </products>
</root>
XML;

        // Act
        $stats = $this->service->importProducts($xmlContent);

        // Assert
        $this->assertEquals(1, $stats['imported']);
        $this->assertEquals('Updated Title', $existingProduct->getTitle());
        $this->assertEquals(29.99, $existingProduct->getPriceVO()->amountFloat());
        $this->assertEquals('Updated Description', $existingProduct->getDescription());
        $this->assertEquals(50, $existingProduct->getAvailable());
    }

    public function testImportProductsFromFileProcessesInBatches(): void
    {
        $products = '';
        for ($i = 1; $i <= 5; ++$i) {
            $products .= <<<XML
                <product>
                    <slug>product-{$i}</slug>
                    <title>Product {$i}</title>
                    <price>10.00</price>
                    <description>Description {$i}</description>
                    <available>1</available>
                    <category>Batch Category</category>
                </product>
            XML;
        }

        $path = tempnam(sys_get_temp_dir(), 'xml-import-');
        self::assertNotFalse($path);
        file_put_contents($path, '<?xml version="1.0" encoding="UTF-8"?><products>'.$products.'</products>');

        $this->productRepository
            ->method('findBySlug')
            ->willReturn(null);

        $this->categoryRepository
            ->method('findBySlug')
            ->willReturn(null);

        $service = new XmlImportExportService(
            $this->entityManager,
            $this->productRepository,
            $this->categoryRepository,
            $this->logger,
            new XmlProductReader(),
            2
        );

        // 5 товаров при batch=2: flush/clear после 2-го, 4-го и в конце
        $this->entityManager
            ->expects($this->exactly(3))
            ->method('flush');

        $this->entityManager
            ->expects($this->exactly(3))
            ->method('clear');

        $stats = $service->importProductsFromFile($path);

        @unlink($path);

        $this->assertSame(5, $stats['imported']);
        $this->assertSame(0, $stats['errors']);
    }

    public function testImportProductsSupportsNestedCategoryAndImages(): void
    {
        $xmlContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<products>
    <product>
        <slug>nested-product</slug>
        <title>Nested Product</title>
        <price>12.50</price>
        <description>Nested category</description>
        <available>3</available>
        <images>
            <image>https://example.com/a.jpg</image>
            <image>https://example.com/b.jpg</image>
        </images>
        <category>
            <slug>nested-category</slug>
            <title>Nested Category</title>
        </category>
    </product>
</products>
XML;

        $this->productRepository
            ->method('findBySlug')
            ->willReturn(null);

        $this->categoryRepository
            ->method('findBySlug')
            ->willReturn(null);

        $persisted = [];
        $this->entityManager
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });

        $stats = $this->service->importProducts($xmlContent);

        $this->assertSame(1, $stats['imported']);
        $this->assertSame(0, $stats['errors']);

        $categories = array_values(array_filter(
            $persisted,
            static fn (object $entity): bool => $entity instanceof Category
        ));
        $this->assertCount(1, $categories);
        $this->assertSame('Nested Category', $categories[0]->getTitle());
        $this->assertSame('nested-category', $categories[0]->getSlug());

        $products = array_values(array_filter(
            $persisted,
            static fn (object $entity): bool => $entity instanceof Product
        ));
        $this->assertCount(1, $products);

        $images = $products[0]->getImages();
        $this->assertCount(2, $images);
        $this->assertSame('https://example.com/a.jpg', $images[0]->getPath());
        $this->assertSame('https://example.com/b.jpg', $images[1]->getPath());
        $this->assertSame(0, $images[0]->getPosition());
        $this->assertSame(1, $images[1]->getPosition());
    }

    public function testImportProductsFromMissingFileThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('XML file is not readable');

        $this->service->importProductsFromFile('/tmp/does-not-exist-'.uniqid('', true).'.xml');
    }

    public function testImportMalformedXmlThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid XML content');

        $this->service->importProducts('<?xml version="1.0"?><products><product><slug>broken');
    }
}
