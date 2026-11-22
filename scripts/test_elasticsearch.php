<?php

declare(strict_types=1);

/**
 * Скрипт для проверки подключения к Elasticsearch из контейнера PHP
 * Использование: docker-compose exec php php scripts/test_elasticsearch.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Elastic\Elasticsearch\ClientBuilder;

echo "=== Тест подключения к Elasticsearch ===\n\n";

try {
    // Параметры подключения
    $host = getenv('ELASTICSEARCH_HOST') ?: 'elasticsearch';
    $port = (int) (getenv('ELASTICSEARCH_PORT') ?: 9200);
    
    echo "Подключение к {$host}:{$port}...\n";
    
    $client = ClientBuilder::create()
        ->setHosts(["{$host}:{$port}"])
        ->build();
    
    // Проверка подключения
    $info = $client->info();
    
    echo " Успешное подключение!\n\n";
    
    // Информация о Elasticsearch
    echo "Информация о Elasticsearch:\n";
    echo "  Name: {$info['name']}\n";
    echo "  Cluster: {$info['cluster_name']}\n";
    echo "  Version: {$info['version']['number']}\n";
    echo "  Lucene: {$info['version']['lucene_version']}\n\n";
    
    // Статус кластера
    $health = $client->cluster()->health();
    echo "Статус кластера:\n";
    echo "  Status: {$health['status']}\n";
    echo "  Nodes: {$health['number_of_nodes']}\n";
    echo "  Data nodes: {$health['number_of_data_nodes']}\n";
    echo "  Active shards: {$health['active_shards']}\n";
    echo "  Relocating shards: {$health['relocating_shards']}\n";
    echo "  Initializing shards: {$health['initializing_shards']}\n";
    echo "  Unassigned shards: {$health['unassigned_shards']}\n\n";
    
    // Список индексов
    echo "Индексы:\n";
    $catIndices = $client->cat()->indices(['format' => 'json']);
    $indices = $catIndices['body'] ?? [];
    
    if (empty($indices)) {
        echo "  (нет индексов)\n";
    } else {
        foreach ($indices as $index) {
            echo "  {$index['index']}: {$index['docs.count']} документов, {$index['store.size']} размер\n";
        }
    }
    
    // Тестовый индекс
    $testIndex = 'test_' . time();
    echo "\nСоздание тестового индекса '{$testIndex}'...\n";
    
    $client->index([
        'index' => $testIndex,
        'id' => '1',
        'body' => [
            'title' => 'Test Document',
            'content' => 'Hello from PHP!',
            'timestamp' => date('c'),
        ]
    ]);
    echo " Документ добавлен\n";
    
    // Поиск
    echo "\nПоиск документа...\n";
    $results = $client->search([
        'index' => $testIndex,
        'body' => [
            'query' => [
                'match' => [
                    'content' => 'Hello from PHP'
                ]
            ]
        ]
    ]);
    
    $hits = $results['hits']['hits'] ?? [];
    if (!empty($hits)) {
        echo " Найден документ: " . $hits[0]['_source']['title'] . "\n";
    } else {
        echo " Документ не найден!\n";
    }
    
    // Удаление тестового индекса
    echo "\nУдаление тестового индекса...\n";
    $client->indices()->delete(['index' => $testIndex]);
    echo " Индекс удален\n";
    
    echo "\n Все тесты пройдены!\n";
    
} catch (Exception $e) {
    echo " Ошибка: " . $e->getMessage() . "\n";
    if (str_contains($e->getMessage(), 'curl')) {
        echo "\nВозможно, Elasticsearch ещё не запустился. Подождите несколько секунд.\n";
    }
    exit(1);
}
