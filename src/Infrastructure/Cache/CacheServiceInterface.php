<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

/**
 * Интерфейс сервиса кэширования.
 */
interface CacheServiceInterface
{
    /**
     * Получить значение из кэша.
     *
     * @template T
     *
     * @param string $key Ключ кэша
     * @param T|null $default Значение по умолчанию
     *
     * @return T|null
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Сохранить значение в кэш.
     *
     * @param string $key Ключ кэша
     * @param mixed $value Значение
     * @param int|null $ttl Время жизни в секундах (null = использовать TTL по умолчанию)
     */
    public function set(string $key, mixed $value, ?int $ttl = null): bool;

    /**
     * Удалить значение из кэша.
     *
     * @param string $key Ключ кэша
     */
    public function delete(string $key): bool;

    /**
     * Проверить, существует ли ключ в кэше.
     *
     * @param string $key Ключ кэша
     */
    public function has(string $key): bool;

    /**
     * Сохранить значение с привязкой к тегу.
     *
     * @param string $key Ключ кэша
     * @param mixed $value Значение
     * @param string $tag Тег для группировки
     * @param int|null $ttl Время жизни в секундах
     */
    public function setWithTag(string $key, mixed $value, string $tag, ?int $ttl = null): bool;

    /**
     * Удалить все кэши с указанным тегом
     *
     * @param string $tag Тег
     */
    public function invalidateByTag(string $tag): bool;

    /**
     * Удалить несколько кэшей по ключам
     *
     * @param array<string> $keys Массив ключей
     */
    public function deleteMultiple(array $keys): bool;

    /**
     * Очистить весь кэш.
     */
    public function clear(): bool;

    /**
     * Удалить ключи по шаблону.
     *
     * @param string $pattern Шаблон ключей (например, 'product_*')
     */
    public function deleteByPattern(string $pattern): bool;
}
