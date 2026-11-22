<?php

declare(strict_types=1);

namespace App\Infrastructure\Idempotency;

use Redis;
use Symfony\Component\HttpFoundation\Request;

/**
 * Реализация проверки идемпотентности через Redis.
 */
final class RedisIdempotencyChecker implements IdempotencyCheckerInterface
{
    private const KEY_PREFIX = 'idempotency:';
    private const TTL_SECONDS = 86400; // 24 часа

    public function __construct(
        private readonly \Redis $redis,
        private readonly int $ttl = 86400
    ) {
    }

    public function isDuplicate(string $idempotencyKey): bool
    {
        return $this->redis->exists($this->getKey($idempotencyKey)) === 1;
    }

    public function getProcessedResult(string $idempotencyKey): mixed
    {
        $result = $this->redis->get($this->getKey($idempotencyKey));

        if ($result === false) {
            return null;
        }

        return json_decode($result, true);
    }

    public function markAsProcessed(string $idempotencyKey, mixed $result, int $ttl = 86400): void
    {
        $key = $this->getKey($idempotencyKey);
        $this->redis->setex(
            $key,
            $ttl,
            json_encode($result, \JSON_THROW_ON_ERROR)
        );
    }

    public function extractKeyFromRequest(Request $request): ?string
    {
        // Извлекаем ключ из заголовка X-Idempotency-Key
        return $request->headers->get('X-Idempotency-Key');
    }

    private function getKey(string $idempotencyKey): string
    {
        return self::KEY_PREFIX.$idempotencyKey;
    }
}
