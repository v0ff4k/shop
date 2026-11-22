<?php

declare(strict_types=1);

namespace Tests\UI\Controller\Admin;

use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SystemMonitorLiveTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $this->entityManager->getConnection()->executeStatement(
            "DELETE FROM \"user\" WHERE username LIKE 'test_monitor_%'"
        );

        if ($this->entityManager->isOpen()) {
            $this->entityManager->close();
        }

        parent::tearDown();
        self::ensureKernelShutdown();
    }

    public function testMonitorPageRendersPrometheusStatusForAdmin(): void
    {
        $admin = $this->createAdmin();
        $this->client->loginUser($admin);
        $this->client->request('GET', '/admin/monitor');

        $this->assertResponseIsSuccessful();
        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('Prometheus:', $content);
        self::assertStringContainsString('Grafana:', $content);
        self::assertStringContainsString('Jaeger:', $content);
    }

    public function testLiveEndpointReturnsAsyncJobsAndLogsForAdmin(): void
    {
        $admin = $this->createAdmin();
        $this->client->loginUser($admin);
        $this->client->request('GET', '/admin/monitor/live', server: [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        self::assertArrayHasKey('asyncJobs', $data);
        self::assertArrayHasKey('summary', $data['asyncJobs']);
        self::assertArrayHasKey('health', $data['asyncJobs']);
        self::assertArrayHasKey('jobs', $data['asyncJobs']);
        self::assertArrayHasKey('logs', $data);
        self::assertArrayHasKey('updatedAt', $data);
    }

    public function testBlockEndpointReturnsCachedDetailsForAdmin(): void
    {
        $admin = $this->createAdmin();
        $this->client->loginUser($admin);
        $this->client->request('GET', '/admin/monitor/block/database', server: [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        self::assertSame('database', $data['block']);
        self::assertArrayHasKey('tableCount', $data['data']);
        self::assertArrayHasKey('databaseSizeHuman', $data['data']);
        self::assertArrayHasKey('cachedAt', $data);
    }

    public function testLogsEndpointReturnsRequestedChunk(): void
    {
        $admin = $this->createAdmin();
        $this->client->loginUser($admin);
        $this->client->request('GET', '/admin/monitor/logs?type=test&limit=5', server: [
            'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
        ]);

        $this->assertResponseIsSuccessful();
        $data = json_decode(
            (string) $this->client->getResponse()->getContent(),
            true,
            512,
            \JSON_THROW_ON_ERROR
        );

        self::assertSame('test', $data['type']);
        self::assertArrayHasKey('lines', $data);
        self::assertArrayHasKey('updatedAt', $data);
    }

    private function createAdmin(): User
    {
        $admin = new User(
            'test_monitor_admin',
            'test_monitor_admin@example.com',
            'password123',
            [User::ROLE_ADMIN]
        );
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        return $admin;
    }
}
