<?php

declare(strict_types=1);

namespace App\Infrastructure\Redis;

use Redis;

/**
 * Redis-счётчик для лёгких операций подсчёта (просмотры, события, метрики).
 *
 * TTL выставляется только при первом инкременте, чтобы окно не продлевалось
 * каждым обращением.
 */
final class RedisCounter
{
    public function __construct(
        private readonly \Redis $redis,
        private readonly string $prefix = 'shop:counter:'
    ) {
    }

    public function increment(string $key, int $ttl = 0): int
    {
        $fullKey = $this->prefix.$key;
        $value = (int) $this->redis->incr($fullKey);

        if ($ttl > 0 && $value === 1) {
            $this->redis->expire($fullKey, $ttl);
        }

        return $value;
    }

    public function get(string $key): int
    {
        $value = $this->redis->get($this->prefix.$key);

        return $value === false ? 0 : (int) $value;
    }

    public function reset(string $key): void
    {
        $this->redis->del($this->prefix.$key);
    }
}
