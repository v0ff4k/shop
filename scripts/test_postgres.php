<?php

declare(strict_types=1);

/**
 * Скрипт для проверки подключения к PostgreSQL из контейнера PHP
 * Использование: docker-compose exec php php scripts/test_postgres.php
 */

echo "=== Тест подключения к PostgreSQL ===\n\n";

try {
    // Параметры подключения
    $host = getenv('POSTGRES_HOST') ?: 'postgres';
    $port = getenv('POSTGRES_PORT') ?: '5432';
    $dbname = getenv('POSTGRES_DB') ?: 'shop';
    $user = getenv('POSTGRES_USER') ?: 'shop_user';
    $password = getenv('POSTGRES_PASSWORD') ?: 'shop_password';
    
    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname};user={$user};password={$password}";
    
    echo "Подключение к {$host}:{$port}/{$dbname}...\n";
    
    $pdo = new PDO($dsn);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo " Успешное подключение!\n\n";
    
    // Проверка версии PostgreSQL
    echo "Информация о PostgreSQL:\n";
    $stmt = $pdo->query("SELECT version()");
    $version = $stmt->fetchColumn();
    echo "  Version: {$version}\n\n";
    
    // Проверка таблиц
    echo "Таблицы в базе данных:\n";
    $stmt = $pdo->query("
        SELECT table_name, table_schema 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
        ORDER BY table_name
    ");
    
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($tables)) {
        echo "  (нет таблиц)\n";
    } else {
        foreach ($tables as $table) {
            echo "  {$table['table_schema']}.{$table['table_name']}\n";
        }
    }
    
    // Проверка количества записей в таблицах
    echo "\nКоличество записей в таблицах:\n";
    $tableCounts = [];
    foreach ($tables as $table) {
        $tableName = $table['table_name'];
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM \"{$tableName}\"");
            $count = $stmt->fetchColumn();
            $tableCounts[$tableName] = $count;
            echo "  {$tableName}: {$count}\n";
        } catch (Exception $e) {
            // Таблица может быть view или не иметь доступа
        }
    }
    
    // Проверка подключений к БД
    echo "\nАктивные подключения к БД:\n";
    $stmt = $pdo->query("
        SELECT count(*) as count, usename 
        FROM pg_stat_activity 
        WHERE datname = current_database()
        GROUP BY usename
    ");
    $connections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($connections as $conn) {
        echo "  {$conn['usename']}: {$conn['count']} подключений\n";
    }
    
    // Проверка размера БД
    echo "\nРазмер базы данных:\n";
    $stmt = $pdo->query("SELECT pg_size_pretty(pg_database_size(current_database()))");
    $size = $stmt->fetchColumn();
    echo "  {$size}\n";
    
    echo "\n Все тесты пройдены!\n";
    
} catch (PDOException $e) {
    echo " Ошибка подключения: " . $e->getMessage() . "\n";
    exit(1);
}
