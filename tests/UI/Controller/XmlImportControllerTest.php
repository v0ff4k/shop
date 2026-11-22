<?php

declare(strict_types=1);

namespace App\Tests\UI\Controller;

use App\Application\Command\XmlImportCommand;
use App\Application\CommandBus\CommandBusInterface;
use App\Infrastructure\ImportExport\XmlImportStorage;
use App\Infrastructure\Messaging\AsyncJobTracker;
use App\Tests\Infrastructure\Cache\InMemoryRedis;
use App\UI\Controller\Admin\XmlImportController;
use App\UI\Request\AdminXmlImportRequestDto;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class XmlImportControllerTest extends TestCase
{
    private CommandBusInterface&MockObject $commandBus;
    private AsyncJobTracker $jobTracker;
    private XmlImportController $controller;
    private string $projectDir;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        $this->jobTracker = new AsyncJobTracker(new InMemoryRedis());
        $this->projectDir = sys_get_temp_dir().'/shop_xml_controller_'.uniqid('', true);

        $this->controller = new XmlImportController(
            $this->commandBus,
            $this->jobTracker,
            $this->createMock(TokenStorageInterface::class),
            new XmlImportStorage($this->projectDir)
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    public function testQueuesXmlFileImportByFilePath(): void
    {
        $xmlContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<products>
    <product>
        <slug>one</slug>
        <title>One</title>
        <price>10.00</price>
    </product>
</products>
XML;

        $path = tempnam(sys_get_temp_dir(), 'xml-import-');
        self::assertNotFalse($path);
        file_put_contents($path, $xmlContent);

        $file = new UploadedFile($path, 'feed.xml', 'application/xml', null, true);
        $dto = AdminXmlImportRequestDto::createFromFiles(['xml_file' => $file]);

        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(
                static function (object $command) use ($xmlContent): void {
                    self::assertInstanceOf(XmlImportCommand::class, $command);
                    self::assertNotEmpty($command->jobId);
                    self::assertNull($command->xmlContent);
                    self::assertFileExists($command->filePath);
                    self::assertSame($xmlContent, file_get_contents($command->filePath));
                }
            );

        $response = ($this->controller)($dto);

        self::assertSame(202, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame('XML import started. You will be notified when complete.', $payload['message']);
        self::assertNotEmpty($payload['job_id']);
        self::assertSame(32, \strlen($payload['job_id']));

        $storedJob = $this->jobTracker->get($payload['job_id']);
        self::assertNotNull($storedJob);
        self::assertSame('xml_import', $storedJob['type']);
        self::assertSame('feed.xml', $storedJob['payload']['file_name']);
        self::assertSame(\strlen($xmlContent), $storedJob['payload']['file_size']);
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
