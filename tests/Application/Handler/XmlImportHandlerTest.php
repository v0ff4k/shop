<?php

declare(strict_types=1);

namespace Tests\Application\Handler;

use App\Application\Command\XmlImportCommand;
use App\Application\Handler\XmlImportHandler;
use App\Application\Service\EmailNotificationService;
use App\Domain\Shop\Repository\CategoryRepositoryInterface;
use App\Domain\Shop\Repository\ProductRepositoryInterface;
use App\Domain\User\Repository\UserRepositoryInterface;
use App\Infrastructure\ImportExport\XmlImportExportService;
use App\Infrastructure\ImportExport\XmlImportStorage;
use App\Infrastructure\ImportExport\XmlProductReader;
use App\Infrastructure\Messaging\AsyncJobTracker;
use App\Tests\Infrastructure\Cache\InMemoryRedis;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Twig\Environment;

final class XmlImportHandlerTest extends TestCase
{
    private AsyncJobTracker $jobTracker;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->jobTracker = new AsyncJobTracker(new InMemoryRedis());
        $this->tempDir = sys_get_temp_dir().'/shop_xml_handler_'.uniqid('', true);

        if (!mkdir($this->tempDir, 0755, true) && !is_dir($this->tempDir)) {
            self::fail('Cannot create temp dir');
        }
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->tempDir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tempDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isDir() ? @rmdir($file->getPathname()) : @unlink($file->getPathname());
        }

        @rmdir($this->tempDir);
    }

    public function testImportsXmlFromFileAndMarksJobCompleted(): void
    {
        $jobId = 'test-job-id';
        $this->jobTracker->create($jobId, 'xml_import');

        $xmlPath = $this->tempDir.'/import.xml';
        file_put_contents(
            $xmlPath,
            '<?xml version="1.0"?><products><product><slug>one</slug><title>One</title><price>10.00</price></product></products>'
        );

        $handler = $this->createHandler();

        $stats = $handler(new XmlImportCommand($jobId, $xmlPath, '0'));

        self::assertSame(1, $stats['imported']);
        self::assertSame(0, $stats['errors']);

        $job = $this->jobTracker->get($jobId);
        self::assertNotNull($job);
        self::assertSame('completed', $job['status']);
        self::assertSame(1, $job['result']['imported']);
        self::assertSame($xmlPath, $job['result']['xml_file_path']);
    }

    public function testImportsLegacyXmlContentWhenFilePathIsMissing(): void
    {
        $jobId = 'legacy-content-job';
        $this->jobTracker->create($jobId, 'xml_import');

        $handler = $this->createHandler();

        $stats = $handler(new XmlImportCommand(
            $jobId,
            null,
            '0',
            '<?xml version="1.0"?><products><product><slug>legacy</slug><title>Legacy</title><price>1.00</price></product></products>'
        ));

        self::assertSame(1, $stats['imported']);
        self::assertFileExists($this->tempDir.'/var/uploads/xml/'.$jobId.'.xml');
    }

    public function testMarksJobFailedWhenFileIsMissing(): void
    {
        $jobId = 'missing-file-job';
        $this->jobTracker->create($jobId, 'xml_import');

        $handler = $this->createHandler();

        $this->expectException(\InvalidArgumentException::class);

        try {
            $handler(new XmlImportCommand($jobId, $this->tempDir.'/missing.xml', '0'));
        } finally {
            $job = $this->jobTracker->get($jobId);
            self::assertNotNull($job);
            self::assertSame('failed', $job['status']);
        }
    }

    private function createHandler(): XmlImportHandler
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $productRepository->method('findBySlug')->willReturn(null);

        $categoryRepository = $this->createMock(CategoryRepositoryInterface::class);
        $categoryRepository->method('findBySlug')->willReturn(null);

        $userRepository = $this->createMock(UserRepositoryInterface::class);
        $userRepository->method('findById')->willReturn(null);

        $xmlService = new XmlImportExportService(
            $entityManager,
            $productRepository,
            $categoryRepository,
            $this->createMock(LoggerInterface::class),
            new XmlProductReader(),
            100
        );

        $emailService = new EmailNotificationService(
            $this->createMock(MessageBusInterface::class),
            $this->createMock(Environment::class),
            'noreply@shop.local'
        );

        $storage = new XmlImportStorage($this->tempDir);

        return new XmlImportHandler(
            $xmlService,
            $this->jobTracker,
            $this->createMock(LoggerInterface::class),
            $userRepository,
            $emailService,
            $storage
        );
    }
}
