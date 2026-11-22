<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\Entity\Product;
use App\Infrastructure\ImportExport\XmlImportExportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class XmlImportExportServiceTest extends KernelTestCase
{
    private XmlImportExportService $xmlService;
    private EntityManagerInterface $entityManager;
    private string $testFilesDir;
    private string $suffix;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->xmlService = $container->get(XmlImportExportService::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->testFilesDir = sys_get_temp_dir().'/shop_xml_test_'.uniqid('', true);
        $this->suffix = substr(uniqid('', true), -8);

        mkdir($this->testFilesDir, 0755, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->testFilesDir.'/*.xml') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->testFilesDir);

        if ($this->entityManager->isOpen()) {
            $this->entityManager->close();
        }

        parent::tearDown();
    }

    public function testExportProductsToXml(): void
    {
        $category = new Category();
        $category->setTitle('Test Category '.$this->suffix);
        $category->setSlug('test-category-'.$this->suffix);
        $this->entityManager->persist($category);

        $product1 = new Product('Test Product 1 '.$this->suffix, '10.99', 'Description 1', $category);
        $product1->setSlug('test-product-1-'.$this->suffix);
        $product2 = new Product('Test Product 2 '.$this->suffix, '20.50', 'Description 2', $category);
        $product2->setSlug('test-product-2-'.$this->suffix);
        $this->entityManager->persist($product1);
        $this->entityManager->persist($product2);
        $this->entityManager->flush();

        $xml = $this->xmlService->exportProducts([], 1000);

        $this->assertStringContainsString('<?xml', $xml);
        $this->assertStringContainsString('Test Product 1 '.$this->suffix, $xml);
        $this->assertStringContainsString('Test Product 2 '.$this->suffix, $xml);
        $this->assertStringContainsString('Test Category '.$this->suffix, $xml);
    }

    public function testImportProductsFromXmlFile(): void
    {
        $xmlContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<products>
    <product>
        <slug>it-imported-product-1-{$this->suffix}</slug>
        <title>Imported Product 1 {$this->suffix}</title>
        <description>Imported Description 1</description>
        <price>15.99</price>
        <category>
            <slug>it-imported-category-{$this->suffix}</slug>
            <title>Imported Category {$this->suffix}</title>
        </category>
    </product>
    <product>
        <slug>it-imported-product-2-{$this->suffix}</slug>
        <title>Imported Product 2 {$this->suffix}</title>
        <description>Imported Description 2</description>
        <price>25.00</price>
        <category>
            <slug>it-imported-category-{$this->suffix}</slug>
            <title>Imported Category {$this->suffix}</title>
        </category>
    </product>
</products>
XML;

        $inputFile = $this->testFilesDir.'/import_test.xml';
        file_put_contents($inputFile, $xmlContent);

        $stats = $this->xmlService->importProductsFromFile($inputFile);

        $this->assertSame(2, $stats['imported']);
        $this->assertSame(0, $stats['errors']);

        $product1 = $this->entityManager
            ->getRepository(Product::class)
            ->findOneBy(['slug' => 'it-imported-product-1-'.$this->suffix]);
        $product2 = $this->entityManager
            ->getRepository(Product::class)
            ->findOneBy(['slug' => 'it-imported-product-2-'.$this->suffix]);

        $this->assertNotNull($product1);
        $this->assertSame('Imported Product 1 '.$this->suffix, $product1->getTitle());
        $this->assertSame('15.99', $product1->getPrice());

        $this->assertNotNull($product2);
        $this->assertSame('Imported Product 2 '.$this->suffix, $product2->getTitle());

        $category = $this->entityManager
            ->getRepository(Category::class)
            ->findOneBy(['slug' => 'it-imported-category-'.$this->suffix]);

        $this->assertNotNull($category);
        $this->assertSame('Imported Category '.$this->suffix, $category->getTitle());
    }

    public function testImportXmlWithInvalidFormatReturnsError(): void
    {
        $inputFile = $this->testFilesDir.'/invalid.xml';
        file_put_contents($inputFile, '<invalid><xml>');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid XML content');

        $this->xmlService->importProductsFromFile($inputFile);
    }
}
