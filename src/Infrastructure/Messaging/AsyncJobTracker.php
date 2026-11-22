<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Infrastructure\Prometheus\PrometheusStorageInterface;

final class AsyncJobTracker
{
    private const PREFIX = 'async_job:';
    private const TTL_SECONDS = 86400;
    private const METRIC_JOB_CREATED = 'async_jobs_created_total';
    private const METRIC_JOB_TRANSITIONS = 'async_job_state_transitions_total';
    private const METRIC_JOB_DURATION = 'async_job_duration_seconds';

    public function __construct(
        private readonly \Redis $redis,
        private readonly ?PrometheusStorageInterface $metrics = null
    ) {
    }

    public function create(string $jobId, string $type, array $payload = []): void
    {
        $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $key = $this->getKey($jobId);

        $this->redis->hMSet($key, [
            'job_id' => $jobId,
            'type' => $type,
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
            'started_at' => '',
            'completed_at' => '',
            'error' => '',
            'result' => json_encode([], \JSON_THROW_ON_ERROR),
            'payload' => json_encode($payload, \JSON_THROW_ON_ERROR),
        ]);
        $this->redis->expire($key, self::TTL_SECONDS);
        $this->recordJobCreated($type);
    }

    public function markRunning(string $jobId): void
    {
        $job = $this->get($jobId);
        $type = $this->normalizeType($job['type'] ?? 'unknown');
        $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $this->redis->hMSet($this->getKey($jobId), [
            'status' => 'running',
            'started_at' => $now,
            'updated_at' => $now,
        ]);
        $this->recordTransition($type, 'running', $job, $now);
    }

    public function markCompleted(string $jobId, array $result = []): void
    {
        $job = $this->get($jobId);
        $type = $this->normalizeType($job['type'] ?? 'unknown');
        $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $this->redis->hMSet($this->getKey($jobId), [
            'status' => 'completed',
            'completed_at' => $now,
            'updated_at' => $now,
            'result' => json_encode($result, \JSON_THROW_ON_ERROR),
            'error' => '',
        ]);
        $this->recordTransition($type, 'completed', $job, $now);
    }

    public function markFailed(string $jobId, string $error): void
    {
        $job = $this->get($jobId);
        $type = $this->normalizeType($job['type'] ?? 'unknown');
        $now = (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM);
        $this->redis->hMSet($this->getKey($jobId), [
            'status' => 'failed',
            'completed_at' => $now,
            'updated_at' => $now,
            'error' => $error,
        ]);
        $this->recordTransition($type, 'failed', $job, $now);
    }

    public function get(string $jobId): ?array
    {
        $data = $this->redis->hGetAll($this->getKey($jobId));
        if ($data === [] || !isset($data['job_id'])) {
            return null;
        }

        return [
            'job_id' => $data['job_id'],
            'type' => $data['type'] ?? '',
            'status' => $data['status'] ?? 'unknown',
            'created_at' => $data['created_at'] ?? null,
            'updated_at' => $data['updated_at'] ?? null,
            'started_at' => $data['started_at'] !== '' ? $data['started_at'] : null,
            'completed_at' => $data['completed_at'] !== '' ? $data['completed_at'] : null,
            'error' => $data['error'] !== '' ? $data['error'] : null,
            'payload' => json_decode($data['payload'] ?? '{}', true, 512, \JSON_THROW_ON_ERROR),
            'result' => json_decode($data['result'] ?? '{}', true, 512, \JSON_THROW_ON_ERROR),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listRecent(int $limit = 20, ?string $type = null): array
    {
        $keys = $this->scanKeys(self::PREFIX.'*', 500);
        if ($keys === []) {
            return [];
        }

        $result = [];

        foreach ($keys as $key) {
            if (\count($result) >= $limit) {
                break;
            }

            $data = $this->redis->hGetAll($key);
            if (!\is_array($data) || !isset($data['job_id'])) {
                continue;
            }

            if ($type !== null && ($data['type'] ?? null) !== $type) {
                continue;
            }

            $job = $this->get($data['job_id']);
            if ($job !== null) {
                $result[] = $job;
            }
        }

        usort(
            $result,
            static fn (array $a, array $b): int => strcmp((string) ($b['updated_at'] ?? ''), (string) ($a['updated_at'] ?? ''))
        );

        return $result;
    }

    /**
     * @return array{
     *     summary: array{total:int,pending:int,running:int,completed:int,failed:int},
     *     queues: array<string, array<string, mixed>>,
     *     jobs: array<int, array<string, mixed>>
     * }
     */
    public function getHealthSnapshot(int $limit = 200): array
    {
        $jobs = $this->listRecent($limit);
        $summary = [
            'total' => \count($jobs),
            'pending' => 0,
            'running' => 0,
            'completed' => 0,
            'failed' => 0,
        ];
        $queues = [];

        foreach ($jobs as $job) {
            $status = (string) ($job['status'] ?? 'unknown');
            $type = $this->normalizeType((string) ($job['type'] ?? 'unknown'));

            if (isset($summary[$status])) {
                ++$summary[$status];
            }

            if (!isset($queues[$type])) {
                $queues[$type] = $this->newQueueSnapshot($type);
            }

            ++$queues[$type]['total'];
            if (isset($queues[$type][$status])) {
                ++$queues[$type][$status];
            }

            $this->accumulateQueueTimings($queues[$type], $job);
        }

        foreach ($queues as $type => $queue) {
            $queues[$type] = $this->finalizeQueueSnapshot($queue);
        }

        ksort($queues);

        return [
            'summary' => $summary,
            'queues' => $queues,
            'jobs' => $jobs,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function scanKeys(string $pattern, int $maxKeys): array
    {
        $iterator = null;
        $keys = [];

        while (true) {
            $batch = $this->redis->scan($iterator, $pattern, 100);
            if ($batch !== false) {
                foreach ($batch as $key) {
                    $keys[] = $key;
                    if (\count($keys) >= $maxKeys) {
                        break 2;
                    }
                }
            }

            if ($iterator === 0) {
                break;
            }
        }

        return $keys;
    }

    private function getKey(string $jobId): string
    {
        return self::PREFIX.$jobId;
    }

    private function recordJobCreated(string $type): void
    {
        if ($this->metrics === null) {
            return;
        }

        $this->metrics->incrementCounter(self::METRIC_JOB_CREATED, [
            'type' => $this->normalizeType($type),
        ]);
    }

    private function recordTransition(string $type, string $status, ?array $job, ?string $finishedAt): void
    {
        if ($this->metrics === null) {
            return;
        }

        $normalizedType = $this->normalizeType($type);
        $this->metrics->incrementCounter(self::METRIC_JOB_TRANSITIONS, [
            'type' => $normalizedType,
            'status' => $status,
        ]);

        if ($job === null || !\in_array($status, ['completed', 'failed'], true)) {
            return;
        }

        $startedAt = $job['started_at'] ?? $job['created_at'] ?? null;
        if (!\is_string($startedAt) || $startedAt === '' || !\is_string($finishedAt) || $finishedAt === '') {
            return;
        }

        $duration = $this->calculateDurationSeconds($startedAt, $finishedAt);
        $this->metrics->observeHistogram(self::METRIC_JOB_DURATION, $duration, [
            'type' => $normalizedType,
            'status' => $status,
        ]);
    }

    private function calculateDurationSeconds(string $startedAt, string $finishedAt): float
    {
        $started = new \DateTimeImmutable($startedAt);
        $finished = new \DateTimeImmutable($finishedAt);

        return max(0.0, (float) $finished->format('U.u') - (float) $started->format('U.u'));
    }

    private function normalizeType(string $type): string
    {
        $type = trim($type);
        if ($type === '') {
            return 'unknown';
        }

        if (str_starts_with($type, 'xml_')) {
            return 'xml';
        }

        if (str_starts_with($type, 'email') || str_contains($type, 'mail')) {
            return 'email';
        }

        if (str_contains($type, 'image')) {
            return 'images';
        }

        return $type;
    }

    private function newQueueSnapshot(string $type): array
    {
        return [
            'type' => $type,
            'total' => 0,
            'pending' => 0,
            'running' => 0,
            'completed' => 0,
            'failed' => 0,
            'oldest_pending_seconds' => null,
            'oldest_running_seconds' => null,
            'last_updated_at' => null,
            'status' => 'ok',
        ];
    }

    private function accumulateQueueTimings(array &$queue, array $job): void
    {
        $updatedAt = $job['updated_at'] ?? null;
        if (\is_string($updatedAt) && $updatedAt !== '') {
            $queue['last_updated_at'] = $this->maxTimestamp((string) $queue['last_updated_at'], $updatedAt);
        }

        if (($job['status'] ?? null) === 'pending') {
            $age = $this->secondsSince($job['created_at'] ?? null);
            if ($age !== null) {
                $queue['oldest_pending_seconds'] = $this->maxSeconds($queue['oldest_pending_seconds'], $age);
            }
        }

        if (($job['status'] ?? null) === 'running') {
            $age = $this->secondsSince($job['started_at'] ?? $job['created_at'] ?? null);
            if ($age !== null) {
                $queue['oldest_running_seconds'] = $this->maxSeconds($queue['oldest_running_seconds'], $age);
            }
        }
    }

    private function finalizeQueueSnapshot(array $queue): array
    {
        $backlog = $queue['pending'] + $queue['running'];
        if ($queue['failed'] > 0 || ($queue['oldest_pending_seconds'] !== null && $queue['oldest_pending_seconds'] > 300)) {
            $queue['status'] = 'warning';
        }

        if ($queue['failed'] > 2 || ($queue['oldest_pending_seconds'] !== null && $queue['oldest_pending_seconds'] > 900)) {
            $queue['status'] = 'error';
        }

        if ($queue['total'] === 0) {
            $queue['status'] = 'ok';
        } elseif ($backlog > 0 && $queue['status'] === 'ok') {
            $queue['status'] = 'warning';
        }

        return $queue;
    }

    private function secondsSince(mixed $timestamp): ?float
    {
        if (!\is_string($timestamp) || $timestamp === '') {
            return null;
        }

        try {
            $value = new \DateTimeImmutable($timestamp);
        } catch (\Exception) {
            return null;
        }

        return max(0.0, (float) (new \DateTimeImmutable())->format('U.u') - (float) $value->format('U.u'));
    }

    private function maxSeconds(mixed $current, float $value): float
    {
        if (!is_numeric($current)) {
            return $value;
        }

        return max((float) $current, $value);
    }

    private function maxTimestamp(?string $current, string $candidate): string
    {
        if ($current === null || $current === '') {
            return $candidate;
        }

        return strcmp($candidate, $current) > 0 ? $candidate : $current;
    }
}
