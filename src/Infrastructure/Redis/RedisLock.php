<?php

declare(strict_types=1);

namespace App\Infrastructure\Redis;

use Redis;

/**
 * Redis lock с безопасным освобождением по токену.
 *
 * Используется для защиты от параллельной отправки одного и того же письма
 * и других коротких критических секций.
 */
final class RedisLock
{
    public function __construct(
        private readonly \Redis $redis,
        private readonly string $prefix = 'shop:lock:'
    ) {
    }

    /**
     * @return string|null Токен блокировки или null, если lock уже занят
     */
    public function acquire(string $key, int $ttl = 30): ?string
    {
        $token = bin2hex(random_bytes(16));
        $result = $this->redis->set(
            $this->prefix.$key,
            $token,
            ['nx', 'ex' => $ttl]
        );

        return $result === true ? $token : null;
    }

    public function release(string $key, string $token): void
    {
        $script = <<<'LUA'
if redis.call('get', KEYS[1]) == ARGV[1] then
    return redis.call('del', KEYS[1])
end
return 0
LUA;

        $this->redis->eval($script, [$this->prefix.$key, $token], 1);
    }
}
