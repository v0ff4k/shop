<?php

declare(strict_types=1);

/**
 * Скрипт для проверки подключения к Redis из контейнера PHP
 * Использование: docker-compose exec php php scripts/test_redis.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "=== Тест подключения к Redis ===\n\n";

try {
    $redis = new Redis();
    
    // Подключение к Redis
    $redisHost = getenv('REDIS_HOST') ?: 'redis';
    $redisPort = (int) (getenv('REDIS_PORT') ?: 6379);
    
    echo "Подключение к {$redisHost}:{$redisPort}...\n";
    
    if (!$redis->connect($redisHost, $redisPort)) {
        throw new Exception("Не удалось подключиться к Redis");
    }
    
    echo " Успешное подключение!\n\n";
    
    // Проверка PING
    $pingResult = $redis->ping();
    echo "PING: {$pingResult}\n";
    
    // Проверка записи/чтения
    $testKey = 'test:' . time();
    $testValue = 'Hello from PHP!';
    
    echo "\nТест записи/чтения:\n";
    $redis->set($testKey, $testValue);
    echo "Записано: {$testKey} = {$testValue}\n";
    
    $readValue = $redis->get($testKey);
    echo "Прочитано: {$testKey} = {$readValue}\n";
    
    if ($readValue === $testValue) {
        echo " Тест записи/чтения пройден!\n";
    } else {
        echo " Тест записи/чтения НЕ пройден!\n";
    }
    
    // Очистка
    $redis->del($testKey);
    
    // Информация о Redis
    echo "\nИнформация о Redis:\n";
    $info = $redis->info();
    echo "  Version: {$info['redis_version']}\n";
    echo "  Mode: {$info['redis_mode']}\n";
    echo "  Connected clients: {$info['connected_clients']}\n";
    echo "  Used memory: " . round($info['used_memory_human'] ?? 0, 2) . "\n";
    echo "  Uptime: {$info['uptime_in_days']} days\n";
    
    // Проверка ключей
    echo "\nКлючи в Redis:\n";
    $keys = $redis->keys('*');
    if (empty($keys)) {
        echo "  (нет ключей)\n";
    } else {
        foreach (array_slice($keys, 0, 10) as $key) {
            $type = $redis->type($key);
            echo "  {$key} (" . getRedisType($type) . ")\n";
        }
        if (count($keys) > 10) {
            echo "  ... и ещё " . (count($keys) - 10) . " ключей\n";
        }
    }
    
    echo "\n Все тесты пройдены!\n";
    
} catch (Exception $e) {
    echo " Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}

function getRedisType(int $type): string
{
    $types = [
        Redis::REDIS_STRING => 'string',
        Redis::REDIS_SET => 'set',
        Redis::REDIS_LIST => 'list',
        Redis::REDIS_ZSET => 'zset',
        Redis::REDIS_HASH => 'hash',
    ];
    return $types[$type] ?? 'unknown';
}
