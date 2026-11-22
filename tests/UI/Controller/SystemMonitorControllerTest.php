<?php

declare(strict_types=1);

namespace App\Tests\UI\Controller;

use App\Infrastructure\Mailing\EmailSpoolService;
use App\Infrastructure\Messaging\AsyncJobTracker;
use App\Tests\Infrastructure\Cache\InMemoryRedis;
use App\UI\Controller\Admin\SystemMonitorController;
use App\UI\Response\ViewResponse;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class SystemMonitorControllerTest extends TestCase
{
    private Connection&MockObject $connection;
    private AsyncJobTracker $jobTracker;
    private SystemMonitorController $controller;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->jobTracker = new AsyncJobTracker(new InMemoryRedis());
        $emailSpoolService = new EmailSpoolService(sys_get_temp_dir(), false);

        $this->controller = new SystemMonitorController(
            null,
            null,
            $this->connection,
            sys_get_temp_dir(),
            $emailSpoolService,
            $this->jobTracker,
            null
        );
    }

    public function testInvokeExposesQueueTelemetryAndAsyncJobs(): void
    {
        $redis = new InMemoryRedis();
        $redis->seedJob([
            'job_id' => 'xml-pending',
            'type' => 'xml_import',
            'status' => 'pending',
            'created_at' => (new \DateTimeImmutable('-9 minutes'))->format(\DateTimeInterface::ATOM),
            'updated_at' => (new \DateTimeImmutable('-9 minutes'))->format(\DateTimeInterface::ATOM),
        ]);
        $redis->seedJob([
            'job_id' => 'xml-failed',
            'type' => 'xml_export',
            'status' => 'failed',
            'created_at' => (new \DateTimeImmutable('-15 minutes'))->format(\DateTimeInterface::ATOM),
            'updated_at' => (new \DateTimeImmutable('-4 minutes'))->format(\DateTimeInterface::ATOM),
            'started_at' => (new \DateTimeImmutable('-10 minutes'))->format(\DateTimeInterface::ATOM),
            'completed_at' => (new \DateTimeImmutable('-4 minutes'))->format(\DateTimeInterface::ATOM),
            'error' => 'boom',
        ]);

        $this->jobTracker = new AsyncJobTracker($redis);
        $this->controller = new SystemMonitorController(
            null,
            null,
            $this->connection,
            sys_get_temp_dir(),
            new EmailSpoolService(sys_get_temp_dir(), false),
            $this->jobTracker,
            null
        );

        $result = $this->createMock(Result::class);
        $this->connection
            ->expects($this->once())
            ->method('executeQuery')
            ->with('SELECT 1')
            ->willReturn($result);
        $this->connection
            ->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn(new PostgreSQLPlatform());
        $this->connection
            ->method('fetchOne')
            ->willReturnCallback(static function (string $sql): string {
                return match (true) {
                    $sql === 'SELECT COUNT(*) FROM product' => '2',
                    str_contains($sql, 'information_schema.tables') => '10',
                    str_contains($sql, 'pg_database_size') => '1048576',
                    default => '0',
                };
            });

        $response = ($this->controller)();

        self::assertInstanceOf(ViewResponse::class, $response);
        self::assertSame('admin/monitor.html.twig', $response->getTemplate());

        $context = $response->getContext();
        self::assertArrayHasKey('status', $context);
        self::assertSame(2, $context['status']['asyncJobs']['summary']['total']);
        self::assertSame(2, $context['status']['queueTelemetry']['summary']['total']);
        self::assertSame('warning', $context['status']['queueTelemetry']['queues']['xml']['status']);
        self::assertSame(2, $context['status']['queueTelemetry']['queues']['xml']['total']);
    }
}
