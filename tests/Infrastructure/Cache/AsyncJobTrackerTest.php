<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Cache;

use App\Infrastructure\Messaging\AsyncJobTracker;
use App\Infrastructure\Prometheus\PrometheusStorageInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class AsyncJobTrackerTest extends TestCase
{
    private InMemoryRedis $redis;
    private PrometheusStorageInterface&MockObject $metrics;
    private AsyncJobTracker $tracker;

    protected function setUp(): void
    {
        $this->redis = new InMemoryRedis();
        $this->metrics = $this->createMock(PrometheusStorageInterface::class);
        $this->tracker = new AsyncJobTracker($this->redis, $this->metrics);
    }

    public function testCreateAndTransitionsEmitTelemetry(): void
    {
        $calls = [];

        $this->metrics
            ->expects($this->exactly(3))
            ->method('incrementCounter')
            ->willReturnCallback(
                static function (string $name, array $labels = [], float $value = 1.0) use (&$calls): void {
                    $calls[] = [
                        'name' => $name,
                        'labels' => $labels,
                        'value' => $value,
                    ];
                }
            );

        $observations = [];
        $this->metrics
            ->expects($this->once())
            ->method('observeHistogram')
            ->willReturnCallback(
                static function (string $name, float $value, array $labels = []) use (&$observations): void {
                    $observations[] = [
                        'name' => $name,
                        'value' => $value,
                        'labels' => $labels,
                    ];
                }
            );

        $this->tracker->create('job-1', 'xml_export', ['foo' => 'bar']);
        $this->tracker->markRunning('job-1');
        $this->tracker->markCompleted('job-1', ['ok' => true]);

        $job = $this->tracker->get('job-1');

        self::assertNotNull($job);
        self::assertSame('completed', $job['status']);
        self::assertSame('xml_export', $job['type']);
        self::assertSame(['foo' => 'bar'], $job['payload']);
        self::assertSame(['ok' => true], $job['result']);

        self::assertSame(
            [
                [
                    'name' => 'async_jobs_created_total',
                    'labels' => ['type' => 'xml'],
                    'value' => 1.0,
                ],
                [
                    'name' => 'async_job_state_transitions_total',
                    'labels' => ['type' => 'xml', 'status' => 'running'],
                    'value' => 1.0,
                ],
                [
                    'name' => 'async_job_state_transitions_total',
                    'labels' => ['type' => 'xml', 'status' => 'completed'],
                    'value' => 1.0,
                ],
            ],
            $calls
        );

        self::assertCount(1, $observations);
        self::assertSame('async_job_duration_seconds', $observations[0]['name']);
        self::assertSame(['type' => 'xml', 'status' => 'completed'], $observations[0]['labels']);
        self::assertGreaterThanOrEqual(0.0, $observations[0]['value']);
    }

    public function testHealthSnapshotGroupsQueuesAndCalculatesAges(): void
    {
        $now = new \DateTimeImmutable();
        $this->redis->seedJob([
            'job_id' => 'xml-pending',
            'type' => 'xml_import',
            'status' => 'pending',
            'created_at' => $now->modify('-10 minutes')->format(\DateTimeInterface::ATOM),
            'updated_at' => $now->modify('-10 minutes')->format(\DateTimeInterface::ATOM),
            'payload' => ['source' => 'feed.xml'],
        ]);
        $this->redis->seedJob([
            'job_id' => 'xml-running',
            'type' => 'xml_export',
            'status' => 'running',
            'created_at' => $now->modify('-20 minutes')->format(\DateTimeInterface::ATOM),
            'updated_at' => $now->modify('-8 minutes')->format(\DateTimeInterface::ATOM),
            'started_at' => $now->modify('-8 minutes')->format(\DateTimeInterface::ATOM),
        ]);
        $this->redis->seedJob([
            'job_id' => 'xml-failed',
            'type' => 'xml_export',
            'status' => 'failed',
            'created_at' => $now->modify('-30 minutes')->format(\DateTimeInterface::ATOM),
            'updated_at' => $now->modify('-5 minutes')->format(\DateTimeInterface::ATOM),
            'started_at' => $now->modify('-12 minutes')->format(\DateTimeInterface::ATOM),
            'completed_at' => $now->modify('-5 minutes')->format(\DateTimeInterface::ATOM),
            'error' => 'boom',
        ]);
        $this->redis->seedJob([
            'job_id' => 'email-done',
            'type' => 'email_send',
            'status' => 'completed',
            'created_at' => $now->modify('-4 minutes')->format(\DateTimeInterface::ATOM),
            'updated_at' => $now->modify('-3 minutes')->format(\DateTimeInterface::ATOM),
            'started_at' => $now->modify('-4 minutes')->format(\DateTimeInterface::ATOM),
            'completed_at' => $now->modify('-3 minutes')->format(\DateTimeInterface::ATOM),
            'result' => ['sent' => true],
        ]);

        $snapshot = $this->tracker->getHealthSnapshot(50);

        self::assertSame(
            [
                'total' => 4,
                'pending' => 1,
                'running' => 1,
                'completed' => 1,
                'failed' => 1,
            ],
            $snapshot['summary']
        );

        self::assertArrayHasKey('xml', $snapshot['queues']);
        self::assertArrayHasKey('email', $snapshot['queues']);

        $xmlQueue = $snapshot['queues']['xml'];
        self::assertSame(3, $xmlQueue['total']);
        self::assertSame(1, $xmlQueue['pending']);
        self::assertSame(1, $xmlQueue['running']);
        self::assertSame(1, $xmlQueue['failed']);
        self::assertSame('warning', $xmlQueue['status']);
        self::assertNotNull($xmlQueue['oldest_pending_seconds']);
        self::assertGreaterThan(0, $xmlQueue['oldest_pending_seconds']);
        self::assertNotNull($xmlQueue['last_updated_at']);

        $emailQueue = $snapshot['queues']['email'];
        self::assertSame(1, $emailQueue['total']);
        self::assertSame(1, $emailQueue['completed']);
        self::assertSame('ok', $emailQueue['status']);

        self::assertCount(4, $snapshot['jobs']);
        self::assertSame('email-done', $snapshot['jobs'][0]['job_id']);
    }
}
