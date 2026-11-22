<?php

declare(strict_types=1);

namespace Tests\UI\Controller\Api;

use App\Domain\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthControllerTest extends WebTestCase
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

    public function testLoginWithValidCredentials(): void
    {
        // Создаем тестового пользователя
        $user = new User(
            'test_auth_user',
            'test_auth@example.com',
            'password123',
            ['ROLE_USER']
        );
        $user->setEnabled(true);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'test_auth_user',
                'password' => 'password123',
            ])
        );

        $this->assertResponseIsSuccessful();
        $content = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('access_token', $content);
        $this->assertArrayHasKey('refresh_token', $content);
        $this->assertArrayHasKey('expires_in', $content);
        $this->assertEquals('Bearer', $content['token_type']);
        $this->assertEquals('test_auth_user', $content['user']['username']);
        $this->assertEquals('test_auth@example.com', $content['user']['email']);
    }

    public function testLoginWithInvalidCredentialsReturns401(): void
    {
        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'nonexistent_user',
                'password' => 'wrong_password',
            ])
        );

        $this->assertResponseStatusCodeSame(401);
        $content = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('error', $content);
        $this->assertNotSame('', (string) $content['error']);
    }

    public function testLoginWithDisabledAccountReturns403(): void
    {
        // Создаем отключенного пользователя
        $user = new User(
            'test_auth_disabled',
            'test_auth_disabled@example.com',
            'password123',
            ['ROLE_USER']
        );
        $user->setEnabled(false);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'test_auth_disabled',
                'password' => 'password123',
            ])
        );

        $this->assertResponseStatusCodeSame(403);
        $content = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Account is disabled', $content['error']);
    }

    public function testRefreshTokenWithValidToken(): void
    {
        // Сначала создаем пользователя и логинимся, чтобы получить refresh токен
        $user = new User(
            'test_auth_refresh',
            'test_auth_refresh@example.com',
            'password123',
            ['ROLE_USER']
        );
        $user->setEnabled(true);
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        // Логинимся
        $this->client->request(
            'POST',
            '/api/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'username' => 'test_auth_refresh',
                'password' => 'password123',
            ])
        );

        $this->assertResponseIsSuccessful();
        $loginResponse = json_decode($this->client->getResponse()->getContent(), true);
        $refreshToken = $loginResponse['refresh_token'];

        // Теперь используем refresh токен для получения нового access токена
        $this->client->request(
            'POST',
            '/api/refresh',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'refresh_token' => $refreshToken,
            ])
        );

        $this->assertResponseIsSuccessful();
        $refreshResponse = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertArrayHasKey('access_token', $refreshResponse);
        $this->assertArrayHasKey('refresh_token', $refreshResponse);
        $this->assertNotEquals($loginResponse['access_token'], $refreshResponse['access_token']);
    }

    public function testRefreshTokenWithInvalidTokenReturns401(): void
    {
        $this->client->request(
            'POST',
            '/api/refresh',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'refresh_token' => 'invalid_token_here',
            ])
        );

        $this->assertResponseStatusCodeSame(401);
        $content = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertEquals('Invalid refresh token', $content['error']);
    }

    private function cleanupTestData(): void
    {
        $conn = $this->entityManager->getConnection();
        $conn->executeStatement('DELETE FROM "user" WHERE username LIKE \'test_auth_%\'');
        $this->entityManager->flush();
    }
}
