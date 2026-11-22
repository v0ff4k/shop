<?php

declare(strict_types=1);

namespace App\Infrastructure\Redis;

use Redis;

/**
 * Redis-лимитер с фиксированным окном.
 *
 * INCR и EXPIRE выполняются одной Lua-командой, поэтому окно не «теряется»
 * при параллельных запросах.
 */
final class RedisRateLimiter
{
    public function __construct(
        private readonly \Redis $redis,
        private readonly string $prefix = 'shop:rate_limit:'
    ) {
    }

    public function hit(string $key, int $limit, int $windowSeconds): RateLimitResult
    {
        $script = <<<'LUA'
local current = redis.call('incr', KEYS[1])
if current == 1 then
    redis.call('expire', KEYS[1], ARGV[1])
end
local ttl = redis.call('ttl', KEYS[1])
return {current, ttl}
LUA;

        /** @var array{0: int|string, 1: int|string} $result */
        $result = $this->redis->eval(
            $script,
            [$this->prefix.$key, $windowSeconds],
            1
        );

        $used = (int) $result[0];
        $retryAfter = max(0, (int) $result[1]);

        return new RateLimitResult(
            allowed: $used <= $limit,
            limit: $limit,
            remaining: max(0, $limit - $used),
            retryAfter: $retryAfter,
            used: $used
        );
    }
}
