<?php

declare(strict_types=1);

namespace App\Infrastructure\Redis;

/**
 * Результат проверки лимита.
 */
final readonly class RateLimitResult
{
    public function __construct(
        public bool $allowed,
        public int $limit,
        public int $remaining,
        public int $retryAfter,
        public int $used
    ) {
    }
}
