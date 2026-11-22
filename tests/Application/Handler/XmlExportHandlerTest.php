<?php

declare(strict_types=1);

namespace Tests\Application\Handler;

use App\Application\Command\XmlExportCommand;
use App\Application\Handler\XmlExportHandler;
use App\Application\Service\EmailNotificationService;
use App\Domain\Shop\Entity\Category;
use App\Domain\Shop\Entity\Product;
use App\Domain\Shop\Repository\CategoryRepositoryInterface;
use App\Domain\Shop\Repository\ProductRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Infrastructure\ImportExport\XmlImportExportService;
use App\Infrastructure\ImportExport\XmlProductReader;
use App\Infrastructure\Messaging\AsyncJobTracker;
use App\Tests\Infrastructure\Cache\InMemoryRedis;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Twig\Environment;

final class XmlExportHandlerTest extends TestCase
{
    private AsyncJobTracker $jobTracker;
    private string $projectDir;

    protected function setUp(): void
    {
        $this->jobTracker = new AsyncJobTracker(new InMemoryRedis());
        $this->projectDir = sys_get_temp_dir().'/shop_xml_export_'.uniqid('', true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    public function testExportsXmlAndMarksJobCompleted(): void
    {
        $jobId = 'export-job-id';
        $this->jobTracker->create($jobId, 'xml_export');

        $category = new Category();
        $category->setTitle('Export Category');
        $category->setSlug('export-category');

        $product = new Product('Export Product', '19.99', 'Export Description', $category);
        $product->setSlug('export-product');

        $handler = $this->createHandler([$product]);

        $xml = $handler(new XmlExportCommand($jobId, [], 100, null, '0'));

        self::assertStringContainsString('<?xml', $xml);
        self::assertStringContainsString('Export Product', $xml);

        $job = $this->jobTracker->get($jobId);
        self::assertNotNull($job);
        self::assertSame('completed', $job['status']);

        $artifactPath = $this->projectDir.'/var/exports/seller/'.$jobId.'.xml';
        self::assertFileExists($artifactPath);
        self::assertStringContainsString('Export Product', file_get_contents($artifactPath));
    }

    /**
     * @param array<int, Product> $products
     */
    private function createHandler(array $products): XmlExportHandler
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);

        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->method('findAll')->willReturn($products);

        $categoryRepository = $this->createMock(CategoryRepositoryInterface::class);

        $xmlService = new XmlImportExportService(
            $entityManager,
            $productRepository,
            $categoryRepository,
            $this->createMock(LoggerInterface::class),
            new XmlProductReader(),
            100
        );

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn(null);

        $emailService = new EmailNotificationService(
            $this->createMock(MessageBusInterface::class),
            $this->createMock(Environment::class),
            'noreply@shop.local'
        );

        return new XmlExportHandler(
            $xmlService,
            $this->jobTracker,
            $this->createMock(LoggerInterface::class),
            $userRepository,
            $emailService,
            $this->projectDir
        );
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        @rmdir($directory);
    }
}
