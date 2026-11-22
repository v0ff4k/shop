<?php

declare(strict_types=1);

namespace Tests\UI\Controller\Api;

use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\Entity\Product;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProductControllerTest extends WebTestCase
{
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;
    private \Doctrine\ORM\EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();

        $this->entityManager = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        if ($this->entityManager->isOpen()) {
            $this->entityManager->close();
        }
        parent::tearDown();
        self::ensureKernelShutdown();
    }

    public function testGetProductsReturnsValidStructure(): void
    {
        $this->client->request('GET', '/api/products');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('products', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertIsArray($data['products']);
        $this->assertGreaterThanOrEqual(\count($data['products']), $data['total']);
    }

    public function testGetProductsReturnsProducts(): void
    {
        // Создаем тестовые данные
        $category = new Category();
        $category->setTitle('Test Category');
        $category->setSlug('test-category');
        $this->entityManager->persist($category);

        $product1 = new Product('Test Product 1', '10.99', 'Description 1', $category);
        $product1->setSlug('test-product-1');
        $product2 = new Product('Test Product 2', '20.50', 'Description 2', $category);
        $product2->setSlug('test-product-2');
        $this->entityManager->persist($product1);
        $this->entityManager->persist($product2);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/products?category='.$category->getId());

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('products', $data);
        $this->assertCount(2, $data['products']);
        $this->assertGreaterThanOrEqual(2, $data['total']);

        // Проверяем первый продукт
        $firstProduct = $data['products'][0];
        $this->assertArrayHasKey('id', $firstProduct);
        $this->assertArrayHasKey('title', $firstProduct);
        $this->assertArrayHasKey('price', $firstProduct);
        $this->assertArrayHasKey('slug', $firstProduct);
        $this->assertArrayHasKey('category', $firstProduct);
    }

    public function testGetProductByIdReturnsProduct(): void
    {
        // Создаем тестовый продукт
        $category = new Category();
        $category->setTitle('Test Category');
        $category->setSlug('test-category');
        $this->entityManager->persist($category);

        $product = new Product('Test Product Detail', '15.99', 'Detailed description', $category);
        $product->setSlug('test-product-detail');
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/product/'.$product->getId());

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertEquals($product->getId(), $data['id']);
        $this->assertEquals('Test Product Detail', $data['title']);
        $this->assertEquals('15.99', $data['price']);
        $this->assertEquals('Detailed description', $data['description']);
        $this->assertEquals('test-product-detail', $data['slug']);
        $this->assertEquals('Test Category', $data['category']['title']);
    }

    public function testGetProductByIdReturns404ForNonexistent(): void
    {
        $this->client->request('GET', '/api/product/999999');

        $this->assertResponseStatusCodeSame(404);
        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $data);
        $this->assertEquals('Product not found', $data['error']);
    }

    public function testGetProductsWithSearchFilter(): void
    {
        // Создаем тестовые продукты
        $category = new Category();
        $category->setTitle('Test Category');
        $category->setSlug('test-category');
        $this->entityManager->persist($category);

        $uniqueProduct = new Product('Unique Searchable Product', '50.00', 'Unique product', $category);
        $uniqueProduct->setSlug('unique-searchable-product');
        $this->entityManager->persist($uniqueProduct);

        $regularProduct = new Product('Regular Product', '30.00', 'Regular product', $category);
        $regularProduct->setSlug('regular-product');
        $this->entityManager->persist($regularProduct);
        $this->entityManager->flush();

        $this->client->request('GET', '/api/products?search=Unique');

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertCount(1, $data['products']);
        $this->assertEquals('Unique Searchable Product', $data['products'][0]['title']);
    }

    public function testGetProductsWithLimitAndOffset(): void
    {
        // Создаем несколько тестовых продуктов
        $category = new Category();
        $category->setTitle('Test Category');
        $category->setSlug('test-category');
        $this->entityManager->persist($category);

        for ($i = 1; $i <= 5; ++$i) {
            $product = new Product("Test Product $i", "$i".'00', "Description $i", $category);
            $product->setSlug('test-product-'.$i);
            $this->entityManager->persist($product);
        }
        $this->entityManager->flush();

        // Запрашиваем первые 2 продукта только из созданной категории
        $this->client->request(
            'GET',
            '/api/products?category='.$category->getId().'&limit=2'
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertCount(2, $data['products']);
        $this->assertGreaterThanOrEqual(5, $data['total']);

        // Запрашиваем продукты со смещением 2, лимит 2
        $this->client->request(
            'GET',
            '/api/products?category='.$category->getId().'&limit=2&offset=2'
        );

        $this->assertResponseIsSuccessful();
        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertCount(2, $data['products']);
        $this->assertGreaterThanOrEqual(5, $data['total']);
    }

    private function cleanupTestData(): void
    {
        $conn = $this->entityManager->getConnection();
        $conn->executeStatement(
            'DELETE FROM product_image WHERE product_id IN ('
            .'SELECT p.id FROM product p '
            .'LEFT JOIN category c ON p.category_id = c.id '
            .'WHERE p.slug LIKE \'test-product%\' '
            .'OR p.slug IN (\'unique-searchable-product\', \'regular-product\') '
            .'OR p.title LIKE \'Test Product%\' '
            .'OR c.slug LIKE \'test-category%\' '
            .'OR c.title LIKE \'Test Category%\')'
        );
        $conn->executeStatement(
            'DELETE FROM product WHERE slug LIKE \'test-product%\' '
            .'OR slug IN (\'unique-searchable-product\', \'regular-product\') '
            .'OR title LIKE \'Test Product%\' '
            .'OR category_id IN ('
            .'SELECT id FROM category WHERE slug LIKE \'test-category%\' OR title LIKE \'Test Category%\')'
        );
        $conn->executeStatement('DELETE FROM category WHERE slug LIKE \'test-category%\' OR title LIKE \'Test Category%\'');
        $this->entityManager->flush();
    }
}
