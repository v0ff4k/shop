<?php

declare(strict_types=1);

namespace App\Tests\Integration\Api;

use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\Entity\Product;
use App\Domain\User\Entity\User;
use App\Infrastructure\Resilience\RedisCircuitBreaker;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Интеграционные тесты для всех API маршрутов.
 *
 * @group api
 */
final class ApiEndpointsTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private \Redis $redis;

    protected function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();

        $container = self::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);
        $this->redis = $container->get(\Redis::class);

        // Очищаем тестовые данные
        $this->cleanupTestData();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();

        if ($this->entityManager->isOpen()) {
            $this->entityManager->close();
        }

        parent::tearDown();
        static::ensureKernelShutdown();
    }

    // ==========================================
    // Тесты Product API
    // ==========================================

    public function testGetProductsReturnsEmptyList(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/products');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('products', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertIsArray($data['products']);
    }

    public function testGetProductsReturnsProducts(): void
    {
        $client = $this->client;
        $marker = 'Test Product API '.uniqid();

        // Создаём тестовые продукты с уникальным маркером для изоляции от фикстур
        $this->createTestProduct($marker.' 1', 100.0);
        $this->createTestProduct($marker.' 2', 200.0);

        $client->request('GET', '/api/products?search='.urlencode($marker));

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('products', $data);
        $this->assertCount(2, $data['products']);
        $this->assertGreaterThanOrEqual(2, $data['total']);

        $titles = array_column($data['products'], 'title');
        $this->assertContains($marker.' 1', $titles);
        $this->assertContains($marker.' 2', $titles);
    }

    public function testGetProductByIdReturnsProduct(): void
    {
        $client = $this->client;
        $product = $this->createTestProduct('Test Product Detail', 150.0);

        $client->request('GET', '/api/product/'.$product->getId());

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals($product->getId(), $data['id']);
        $this->assertEquals('Test Product Detail', $data['title']);
        $this->assertEquals(150.0, $data['price']);
    }

    public function testGetProductByIdReturns404ForNonExistent(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/product/999999');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testGetProductsWithSearchFilter(): void
    {
        $client = $this->client;
        $this->createTestProduct('Unique Searchable Product', 50.0);
        $this->createTestProduct('Another Product', 60.0);

        $client->request('GET', '/api/products?search=Unique');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertCount(1, $data['products']);
        $this->assertEquals('Unique Searchable Product', $data['products'][0]['title']);
    }

    public function testGetProductsWithLimit(): void
    {
        $client = $this->client;
        $this->createTestProduct('Product 1', 10.0);
        $this->createTestProduct('Product 2', 20.0);
        $this->createTestProduct('Product 3', 30.0);

        $client->request('GET', '/api/products?limit=2');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertCount(2, $data['products']);
    }

    // ==========================================
    // Тесты Circuit Breaker API
    // ==========================================

    public function testGetCircuitBreakerStatsReturnsEmptyList(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/circuit-breaker/stats');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }

    public function testGetCircuitBreakerStateForNewService(): void
    {
        $client = $this->client;
        $client->request('GET', '/api/circuit-breaker/test_service/state');

        // Должен вернуть состояние (даже если сервис новый)
        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('success', $data);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertEquals('test_service', $data['data']['service']);
    }

    public function testResetCircuitBreaker(): void
    {
        $client = $this->client;

        // Сначала создаём circuit breaker с ошибками
        $circuitBreaker = new RedisCircuitBreaker($this->redis, 'test_reset_service');
        for ($i = 0; $i < 5; ++$i) {
            $circuitBreaker->recordFailure();
        }

        // Проверяем что он в состоянии ошибок
        $state = $circuitBreaker->getState();
        $this->assertNotEquals('closed', $state->value);

        // Сбрасываем через API
        $client->request('POST', '/api/circuit-breaker/test_reset_service/reset');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($data['success']);

        // Проверяем что circuit breaker сброшен
        $stateAfterReset = $circuitBreaker->getState();
        $this->assertEquals('closed', $stateAfterReset->value);
    }

    public function testResetAllCircuitBreakers(): void
    {
        $client = $this->client;

        // Создаём несколько circuit breaker с ошибками
        $cb1 = new RedisCircuitBreaker($this->redis, 'test_reset_all_1');
        $cb2 = new RedisCircuitBreaker($this->redis, 'test_reset_all_2');

        for ($i = 0; $i < 5; ++$i) {
            $cb1->recordFailure();
            $cb2->recordFailure();
        }

        // Сбрасываем все
        $client->request('POST', '/api/circuit-breaker/reset-all');

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertGreaterThanOrEqual(2, $data['reset_count']);

        // Проверяем что все сброшены
        $this->assertEquals('closed', $cb1->getState()->value);
        $this->assertEquals('closed', $cb2->getState()->value);
    }

    // ==========================================
    // Тесты Auth API
    // ==========================================

    public function testLoginWithInvalidCredentialsReturns401(): void
    {
        $client = $this->client;
        $client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => 'nonexistent_user',
            'password' => 'wrong_password',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('error', $data);
        $this->assertIsString($data['error']);
        $this->assertNotSame('', trim($data['error']));
    }

    public function testLoginWithValidCredentialsReturnsToken(): void
    {
        $client = $this->client;

        // Создаём тестового пользователя
        $user = $this->createTestUser('test_api_user', 'test@example.com', 'password123');

        $client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => 'test_api_user',
            'password' => 'password123',
        ]));

        $this->assertResponseIsSuccessful();
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('access_token', $data);
        $this->assertArrayHasKey('refresh_token', $data);
        $this->assertArrayHasKey('expires_in', $data);
        $this->assertEquals('Bearer', $data['token_type']);
        $this->assertEquals('test_api_user', $data['user']['username']);
        $this->assertEquals('test@example.com', $data['user']['email']);
    }

    public function testLoginWithDisabledAccountReturns403(): void
    {
        $client = $this->client;

        // Создаём отключённого пользователя
        $user = $this->createTestUser('disabled_user', 'disabled@example.com', 'password123');
        $user->setEnabled(false);
        $this->entityManager->flush();

        $client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => 'disabled_user',
            'password' => 'password123',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals('Account is disabled', $data['error']);
    }

    public function testRefreshTokenWithInvalidTokenReturns401(): void
    {
        $client = $this->client;
        $client->request('POST', '/api/refresh', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'refresh_token' => 'invalid_token_here',
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals('Invalid refresh token', $data['error']);
    }

    public function testRefreshTokenWithMissingTokenReturns400(): void
    {
        $client = $this->client;
        $client->request('POST', '/api/refresh', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $data = json_decode($client->getResponse()->getContent(), true);

        $this->assertEquals('Refresh token is required', $data['error']);
    }

    public function testRefreshTokenWithValidToken(): void
    {
        $client = $this->client;

        // Сначала логинимся
        $this->createTestUser('refresh_test_user', 'refresh@example.com', 'password123');

        $client->request('POST', '/api/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => 'refresh_test_user',
            'password' => 'password123',
        ]));

        $this->assertResponseIsSuccessful();
        $loginData = json_decode($client->getResponse()->getContent(), true);

        // Теперь обновляем токен
        $client->request('POST', '/api/refresh', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'refresh_token' => $loginData['refresh_token'],
        ]));

        $this->assertResponseIsSuccessful();
        $refreshData = json_decode($client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('access_token', $refreshData);
        $this->assertArrayHasKey('refresh_token', $refreshData);
        $this->assertNotEquals($loginData['access_token'], $refreshData['access_token']);
    }

    private function cleanupTestData(): void
    {
        // Очищаем circuit breaker ключи
        $pattern = 'circuit_breaker:test_*:state';
        $keys = $this->redis->keys($pattern);
        foreach ($keys as $key) {
            $this->redis->del($key);
            $this->redis->del(str_replace(':state', ':failures', $key));
            $this->redis->del(str_replace(':state', ':successes', $key));
            $this->redis->del(str_replace(':state', ':last_failure', $key));
            $this->redis->del(str_replace(':state', ':next_retry', $key));
        }

        // Очищаем тестовые продукты и категории
        $conn = $this->entityManager->getConnection();
        $conn->executeStatement('DELETE FROM product_image WHERE product_id IN (SELECT id FROM product WHERE slug LIKE \'test-%\')');
        $conn->executeStatement('DELETE FROM product WHERE slug LIKE \'test-%\'');
        $conn->executeStatement('DELETE FROM product WHERE title LIKE \'Test Product%\'');
        $conn->executeStatement('DELETE FROM category WHERE slug LIKE \'test-%\' OR title LIKE \'Test Category API%\'');
        $conn->executeStatement('DELETE FROM "user" WHERE username = \'test_api_user\'');
        $this->entityManager->flush();
    }

    // ==========================================
    // Helper методы
    // ==========================================

    private function createTestProduct(string $title, float $price): Product
    {
        $suffix = substr(md5($title.microtime(true)), 0, 8);

        $category = new Category();
        $category->setTitle('Test Category API '.$suffix);
        $category->setSlug('test-category-api-'.$suffix);
        $this->entityManager->persist($category);

        $product = new Product($title, number_format($price, 2, '.', ''), 'Test description', $category);
        $product->setSlug('test-'.uniqid());
        $product->setAvailable(10);
        $product->setCategory($category);
        $this->entityManager->persist($product);
        $this->entityManager->flush();

        return $product;
    }

    private function createTestUser(string $username, string $email, string $password): User
    {
        $conn = $this->entityManager->getConnection();
        $conn->executeStatement('DELETE FROM "user" WHERE username = :username OR email = :email', [
            'username' => $username,
            'email' => $email,
        ]);

        $user = new User($username, $email, $password, ['ROLE_USER']);
        $user->setEnabled(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
