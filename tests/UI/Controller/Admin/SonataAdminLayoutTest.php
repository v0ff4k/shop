<?php

declare(strict_types=1);

namespace Tests\UI\Controller\Admin;

use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SonataAdminLayoutTest extends WebTestCase
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
            "DELETE FROM \"user\" WHERE username LIKE 'test_sonata_layout_%'"
        );

        if ($this->entityManager->isOpen()) {
            $this->entityManager->close();
        }

        parent::tearDown();
        self::ensureKernelShutdown();
    }

    public function testDashboardUsesProjectLayoutFromTemplatesDirectory(): void
    {
        $admin = new User(
            'test_sonata_layout_admin',
            'test_sonata_layout_admin@example.com',
            'password123',
            [User::ROLE_ADMIN]
        );
        $this->entityManager->persist($admin);
        $this->entityManager->flush();

        $this->client->loginUser($admin);
        $this->client->request('GET', '/admin/dashboard');

        $this->assertResponseIsSuccessful();

        $content = (string) $this->client->getResponse()->getContent();
        self::assertStringContainsString('css/main.css', $content);
        self::assertStringContainsString('Home', $content);
    }
}
