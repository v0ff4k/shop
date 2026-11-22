<?php

declare(strict_types=1);

namespace Tests\UI\Controller\Seller;

use App\Domain\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class XmlImportControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        self::ensureKernelShutdown();
        $this->client = self::createClient();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();

        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];

        if ($this->entityManager->isOpen()) {
            $this->entityManager->close();
        }

        parent::tearDown();
        self::ensureKernelShutdown();
    }

    public function testXmlImportRequiresAuthentication(): void
    {
        $file = $this->createUploadedFile(
            '<?xml version="1.0"?><products></products>',
            'test.xml',
            'text/xml'
        );

        $this->client->request('POST', '/seller/import/xml', [], ['xml_file' => $file]);

        $statusCode = $this->client->getResponse()->getStatusCode();
        self::assertContains($statusCode, [302, 401, 403]);
    }

    public function testXmlImportRequiresSellerRole(): void
    {
        $user = $this->createUser('test_seller_regular', ['ROLE_USER']);
        $this->client->loginUser($user);

        $file = $this->createUploadedFile(
            '<?xml version="1.0"?><products></products>',
            'test.xml',
            'text/xml'
        );

        $this->client->request('POST', '/seller/import/xml', [], ['xml_file' => $file]);

        $this->assertResponseStatusCodeSame(403);
    }

    public function testXmlImportSuccessForSeller(): void
    {
        $seller = $this->createUser('test_seller_user', ['ROLE_SELLER']);
        $this->client->loginUser($seller);

        $xmlContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<products>
    <product>
        <slug>test-import-product</slug>
        <title>Test Import Product</title>
        <description>Test Description</description>
        <price>19.99</price>
        <category>
            <slug>test-import-category</slug>
            <title>Test Import Category</title>
        </category>
    </product>
</products>
XML;

        $file = $this->createUploadedFile($xmlContent, 'test-import.xml', 'text/xml');

        $this->client->request('POST', '/seller/import/xml', [], ['xml_file' => $file]);

        $this->assertResponseStatusCodeSame(202);

        $data = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame('XML import started. You will be notified when complete.', $data['message']);
        self::assertIsString($data['job_id']);
        self::assertSame(32, \strlen($data['job_id']));

        $conn = $this->entityManager->getConnection();
        $queued = (int) $conn->fetchOne(
            "SELECT COUNT(*) FROM messenger_messages WHERE queue_name = 'xml' AND body LIKE :body",
            ['body' => '%XmlImportCommand%']
        );
        self::assertGreaterThan(0, $queued);
    }

    public function testXmlImportRejectsNonXmlFile(): void
    {
        $seller = $this->createUser('test_seller_user2', ['ROLE_SELLER']);
        $this->client->loginUser($seller);

        $file = $this->createUploadedFile('This is not an XML file', 'test.txt', 'text/plain');

        $this->client->request('POST', '/seller/import/xml', [], ['xml_file' => $file]);

        $this->assertResponseStatusCodeSame(400);
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $username, array $roles): User
    {
        $user = new User(
            $username,
            $username.'@example.com',
            'password123',
            $roles
        );
        $user->setEnabled(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function createUploadedFile(string $content, string $name, string $mimeType): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'shop_xml_seller_');
        self::assertNotFalse($path);
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return new UploadedFile($path, $name, $mimeType, null, true);
    }

    private function cleanupTestData(): void
    {
        $conn = $this->entityManager->getConnection();
        $conn->executeStatement('DELETE FROM "user" WHERE username LIKE :prefix', ['prefix' => 'test_seller_%']);
        $conn->executeStatement(
            "DELETE FROM messenger_messages WHERE queue_name = 'xml' AND body LIKE :body",
            ['body' => '%XmlImportCommand%']
        );
    }
}
