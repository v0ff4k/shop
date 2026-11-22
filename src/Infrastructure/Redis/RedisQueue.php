<?php

declare(strict_types=1);

namespace App\Infrastructure\Redis;

use Redis;

/**
 * Простая Redis-очередь (LIST) для низкоприоритетных задач.
 *
 * Используется там, где RabbitMQ избыточен: например, для пакетной
 * персистенции просмотров товаров.
 */
final class RedisQueue
{
    public function __construct(
        private readonly \Redis $redis,
        private readonly string $prefix = 'shop:queue:'
    ) {
    }

    public function push(string $queue, string $payload, int $maxLength = 0): int
    {
        $key = $this->getKey($queue);
        $size = (int) $this->redis->lPush($key, $payload);

        if ($maxLength > 0) {
            $this->redis->lTrim($key, 0, $maxLength - 1);
        }

        return $size;
    }

    public function pop(string $queue): ?string
    {
        $value = $this->redis->rPop($this->getKey($queue));

        return $value === false ? null : (string) $value;
    }

    public function size(string $queue): int
    {
        return (int) $this->redis->lLen($this->getKey($queue));
    }

    public function clear(string $queue): void
    {
        $this->redis->del($this->getKey($queue));
    }

    private function getKey(string $queue): string
    {
        return $this->prefix.$queue;
    }
}
