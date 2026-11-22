<?php

declare(strict_types=1);

namespace App\Tests\UI\Controller;

use App\Application\Command\XmlExportCommand;
use App\Application\CommandBus\CommandBusInterface;
use App\Infrastructure\Messaging\AsyncJobTracker;
use App\Tests\Infrastructure\Cache\InMemoryRedis;
use App\UI\Controller\Admin\XmlExportController;
use App\UI\Request\AdminXmlExportRequestDto;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final class XmlExportControllerTest extends TestCase
{
    private CommandBusInterface&MockObject $commandBus;
    private AsyncJobTracker $jobTracker;
    private XmlExportController $controller;

    protected function setUp(): void
    {
        $this->commandBus = $this->createMock(CommandBusInterface::class);
        $this->jobTracker = new AsyncJobTracker(new InMemoryRedis());
        $this->controller = new XmlExportController(
            $this->commandBus,
            $this->jobTracker,
            $this->createMock(TokenStorageInterface::class)
        );
    }

    public function testParsesProductIdsAndDispatchesExport(): void
    {
        $this->commandBus
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(
                static function (object $command): void {
                    self::assertInstanceOf(XmlExportCommand::class, $command);
                    self::assertNotEmpty($command->jobId);
                    self::assertSame([1, 2, 3, 4], $command->productIds);
                    self::assertSame(6000, $command->limit);
                    self::assertSame('/tmp/products.xml', $command->outputPath);
                    self::assertSame('1', $command->sellerUserId);
                }
            );

        $request = new Request([], [
            'product_ids' => "1, 2\n3;0,-5, 4",
            'limit' => '6000',
            'output_path' => '  /tmp/products.xml  ',
        ]);

        $dto = AdminXmlExportRequestDto::fromRequest($request);
        $response = ($this->controller)($dto);

        self::assertSame(202, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertSame([1, 2, 3, 4], $payload['product_ids']);
        self::assertSame(6000, $payload['limit']);
        self::assertNotEmpty($payload['job_id']);
        self::assertSame(32, \strlen($payload['job_id']));

        $storedJob = $this->jobTracker->get($payload['job_id']);
        self::assertNotNull($storedJob);
        self::assertSame('xml_export', $storedJob['type']);
        self::assertSame(
            [
                'product_ids' => [1, 2, 3, 4],
                'limit' => 6000,
                'output_path' => '/tmp/products.xml',
                'user_type' => 'admin',
            ],
            $storedJob['payload']
        );
    }
}
