<?php

declare(strict_types=1);

namespace Tests\UI\Controller\Shop;

use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\Entity\Product;
use App\Infrastructure\Cache\RedisCacheService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProductIndexControllerTest extends WebTestCase
{
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;
    private \Doctrine\ORM\EntityManagerInterface $entityManager;
    private RedisCacheService $cache;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();

        $this->entityManager = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $this->cache = self::getContainer()->get(RedisCacheService::class);
        $this->cache->clear();
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

    public function testIndexPageLoadsSuccessfully(): void
    {
        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('h1');
    }

    public function testProductIndexShowsProducts(): void
    {
        // Создаем тестовые данные
        $category = new Category();
        $category->setTitle('Test Shop Category');
        $category->setSlug('test-shop-category');
        $this->entityManager->persist($category);

        $product1 = new Product('Test Shop Product 1', '15.99', 'Description 1', $category);
        $product1->setSlug('test-shop-product-1');
        $product2 = new Product('Test Shop Product 2', '25.50', 'Description 2', $category);
        $product2->setSlug('test-shop-product-2');
        $this->entityManager->persist($product1);
        $this->entityManager->persist($product2);
        $this->entityManager->flush();

        $this->client->request('GET', '/products');

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        $this->assertStringContainsString('Test Shop Product 1', $content);
        $this->assertStringContainsString('Test Shop Product 2', $content);
    }

    public function testProductIndexShowsPaginationWhenManyProducts(): void
    {
        // Создаем много тестовых продуктов для проверки пагинации
        $category = new Category();
        $category->setTitle('Test Shop Category');
        $category->setSlug('test-shop-category');
        $this->entityManager->persist($category);

        for ($i = 1; $i <= 25; ++$i) { // 25 продуктов
            $this->entityManager->persist(
                (new Product("Test Shop Product $i", "$i".'99', "Description $i", $category))
                    ->setSlug('test-shop-product-'.$i)
            );
        }
        $this->entityManager->flush();

        $this->client->request('GET', '/products?page=1');

        $this->assertResponseIsSuccessful();
        // Проверяем, что есть элементы пагинации
        $this->assertSelectorExists('.pagination');
        // На странице выводится ровно limit карточек.
        $this->assertSelectorCount(20, '.card-title');
    }

    public function testProductIndexFilterByCategory(): void
    {
        // Создаем две категории
        $category1 = new Category();
        $category1->setTitle('Category One');
        $category1->setSlug('category-one');
        $this->entityManager->persist($category1);

        $category2 = new Category();
        $category2->setTitle('Category Two');
        $category2->setSlug('category-two');
        $this->entityManager->persist($category2);

        // Создаем продукты в каждой категории
        $this->entityManager->persist(
            (new Product('Product in Cat 1', '10.00', 'Description', $category1))
                ->setSlug('product-cat-1')
        );
        $this->entityManager->persist(
            (new Product('Product in Cat 2', '20.00', 'Description', $category2))
                ->setSlug('product-cat-2')
        );
        $this->entityManager->flush();

        $this->client->request('GET', '/products?category_id='.$category1->getId());

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Product in Cat 1', (string) $this->client->getResponse()->getContent());
        $this->assertStringNotContainsString('Product in Cat 2', (string) $this->client->getResponse()->getContent());
    }

    private function cleanupTestData(): void
    {
        $this->cache->clear();
        $conn = $this->entityManager->getConnection();
        $conn->executeStatement("DELETE FROM product_image WHERE product_id IN (SELECT id FROM product WHERE slug LIKE 'test-shop-%' OR slug LIKE 'product-cat-%')");
        $conn->executeStatement("DELETE FROM product WHERE slug LIKE 'test-shop-%' OR slug LIKE 'product-cat-%' OR title LIKE 'Test Shop Product %'");
        $conn->executeStatement("DELETE FROM category WHERE slug LIKE 'test-shop-category%' OR slug IN ('category-one', 'category-two') OR title IN ('Category One', 'Category Two', 'Test Shop Category')");
        $this->entityManager->flush();
    }
}
