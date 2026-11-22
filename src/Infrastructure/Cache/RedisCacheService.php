<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use Redis;

/**
 * Redis реализация сервиса кэширования с поддержкой тегов.
 *
 * Теги реализованы через Redis Sets для группировки ключей
 */
final class RedisCacheService implements CacheServiceInterface
{
    private const KEY_PREFIX = 'cache:';
    private const TAG_PREFIX = 'cache_tag:';
    private const DEFAULT_TTL = 3600; // 1 час

    public function __construct(
        private readonly \Redis $redis,
        private readonly int $defaultTtl = self::DEFAULT_TTL,
        private readonly string $prefix = 'shop_'
    ) {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $fullKey = $this->getFullKey($key);
        $value = $this->redis->get($fullKey);

        if ($value === false) {
            return $default;
        }

        // return with all classes. from setWithTag()
        return unserialize($value, ['allowed_classes' => true, 'max_depth' => 10]);
    }

    /**
     * @todo test  if $value = false ???
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $fullKey = $this->getFullKey($key);
        $ttl = $ttl ?? $this->defaultTtl;

        $serialized = serialize($value);

        return $this->redis->setex($fullKey, $ttl, $serialized);
    }

    public function delete(string $key): bool
    {
        $fullKey = $this->getFullKey($key);

        return $this->redis->del($fullKey) > 0;
    }

    public function has(string $key): bool
    {
        $fullKey = $this->getFullKey($key);

        return $this->redis->exists($fullKey) > 0;
    }

    /**
     * @todo test  if $value = false ???
     */
    public function setWithTag(string $key, mixed $value, string $tag, ?int $ttl = null): bool
    {
        $fullKey = $this->getFullKey($key);
        $tagKey = $this->getTagKey($tag);
        $ttl = $ttl ?? $this->defaultTtl;

        // @TODO check for data in $value for future safe unserialize() func

        // Сохраняем значение
        $serialized = serialize($value);
        $result = $this->redis->setex($fullKey, $ttl, $serialized);

        if ($result) {
            // Добавляем ключ в множество тегов
            $this->redis->sAdd($tagKey, $fullKey);

            // Устанавливаем TTL для тега (такой же как у ключа)
            $this->redis->expire($tagKey, $ttl);
        }

        return $result;
    }

    public function invalidateByTag(string $tag): bool
    {
        $tagKey = $this->getTagKey($tag);

        // Получаем все ключи с этим тегом
        $keys = $this->redis->sMembers($tagKey);

        if (empty($keys)) {
            return true;
        }

        // Удаляем все ключи
        $this->redis->del($keys);

        // Удаляем множество тегов
        $this->redis->del($tagKey);

        return true;
    }

    public function deleteMultiple(array $keys): bool
    {
        if (empty($keys)) {
            return true;
        }

        $fullKeys = array_map(fn ($key) => $this->getFullKey($key), $keys);

        return $this->redis->del($fullKeys) > 0;
    }

    public function clear(): bool
    {
        // Находим все ключи с префиксом кэша (production-safe через SCAN)
        $keys = $this->scanKeys($this->prefix.self::KEY_PREFIX.'*');

        if (!empty($keys)) {
            $this->redis->del($keys);
        }

        // Находим и удаляем все теги
        $tagKeys = $this->scanKeys($this->prefix.self::TAG_PREFIX.'*');
        if (!empty($tagKeys)) {
            $this->redis->del($tagKeys);
        }

        return true;
    }

    /**
     * Удалить ключи по шаблону.
     */
    public function deleteByPattern(string $pattern): bool
    {
        $keys = $this->scanKeys($this->prefix.self::KEY_PREFIX.$pattern);

        if (!empty($keys)) {
            $this->redis->del($keys);
        }

        return true;
    }

    /**
     * Получить статистику кэша.
     */
    public function getStats(): array
    {
        $keys = $this->scanKeys($this->prefix.self::KEY_PREFIX.'*');
        $tagKeys = $this->scanKeys($this->prefix.self::TAG_PREFIX.'*');

        $memoryUsage = 0;
        foreach ($keys as $key) {
            $keyMemory = $this->redis->rawCommand('MEMORY', 'USAGE', $key);
            $memoryUsage += \is_int($keyMemory) ? $keyMemory : 0;
        }

        return [
            'total_keys' => \count($keys),
            'total_tags' => \count($tagKeys),
            'memory_usage_bytes' => $memoryUsage,
            'memory_usage_mb' => round($memoryUsage / 1024 / 1024, 2),
        ];
    }

    /**
     * Получить полный ключ с префиксом
     */
    private function getFullKey(string $key): string
    {
        return $this->prefix.self::KEY_PREFIX.$key;
    }

    /**
     * Получить ключ тега.
     */
    private function getTagKey(string $tag): string
    {
        return $this->prefix.self::TAG_PREFIX.$tag;
    }

    /**
     * @return array<int, string>
     */
    private function scanKeys(string $pattern): array
    {
        $iterator = null;
        $keys = [];

        while (true) {
            $batch = $this->redis->scan($iterator, $pattern, 200);
            if ($batch !== false) {
                foreach ($batch as $key) {
                    $keys[] = $key;
                }
            }

            if ($iterator === 0) {
                break;
            }
        }

        return $keys;
    }
}
