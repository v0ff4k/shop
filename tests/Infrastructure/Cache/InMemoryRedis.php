<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Cache;

final class InMemoryRedis extends \Redis
{
    /**
     * @var array<string, array<string, string>>
     */
    private array $hashes = [];

    public function seedJob(array $job): void
    {
        $jobId = (string) ($job['job_id'] ?? '');
        if ($jobId === '') {
            throw new \InvalidArgumentException('job_id is required');
        }

        $this->hashes[$this->getKey($jobId)] = [
            'job_id' => $jobId,
            'type' => (string) ($job['type'] ?? 'unknown'),
            'status' => (string) ($job['status'] ?? 'unknown'),
            'created_at' => (string) ($job['created_at'] ?? ''),
            'updated_at' => (string) ($job['updated_at'] ?? ''),
            'started_at' => (string) ($job['started_at'] ?? ''),
            'completed_at' => (string) ($job['completed_at'] ?? ''),
            'error' => (string) ($job['error'] ?? ''),
            'result' => json_encode($job['result'] ?? [], \JSON_THROW_ON_ERROR),
            'payload' => json_encode($job['payload'] ?? [], \JSON_THROW_ON_ERROR),
        ];
    }

    public function hMSet(string $key, array $fieldvals): \Redis|bool
    {
        $current = $this->hashes[$key] ?? [];
        foreach ($fieldvals as $field => $value) {
            $current[(string) $field] = (string) $value;
        }

        $this->hashes[$key] = $current;

        return true;
    }

    public function expire(string $key, int $timeout, ?string $mode = null): \Redis|bool
    {
        return isset($this->hashes[$key]);
    }

    public function hGetAll(string $key): array|false
    {
        return $this->hashes[$key] ?? [];
    }

    public function scan(int|string|null &$iterator, ?string $pattern = null, int $count = 0, ?string $type = null): array|false
    {
        $keys = array_keys($this->hashes);

        if (\is_string($pattern) && $pattern !== '' && str_ends_with($pattern, '*')) {
            $prefix = substr($pattern, 0, -1);
            $keys = array_values(array_filter(
                $keys,
                static fn (string $key): bool => str_starts_with($key, $prefix)
            ));
        }

        sort($keys);
        $iterator = 0;

        return $keys;
    }

    private function getKey(string $jobId): string
    {
        return 'async_job:'.$jobId;
    }
}
