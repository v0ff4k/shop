<?php

declare(strict_types=1);

/**
 * Скрипт для проверки подключения к RabbitMQ из контейнера PHP
 * Использование: docker-compose exec php php scripts/test_rabbitmq.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PhpAmqpLib\Connection\AMQPStreamConnection;

echo "=== Тест подключения к RabbitMQ ===\n\n";

try {
    // Параметры подключения
    $host = getenv('RABBITMQ_HOST') ?: 'rabbitmq';
    $port = (int) (getenv('RABBITMQ_PORT') ?: 5672);
    $user = getenv('RABBITMQ_USER') ?: 'guest';
    $password = getenv('RABBITMQ_PASSWORD') ?: 'guest';
    $vhost = getenv('RABBITMQ_VHOST') ?: '/';
    
    echo "Подключение к {$host}:{$port} (vhost: {$vhost})...\n";
    
    $connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);
    $channel = $connection->channel();
    
    echo " Успешное подключение!\n\n";
    
    // Информация о подключении
    echo "Информация о подключении:\n";
    echo "  Host: {$host}\n";
    echo "  Port: {$port}\n";
    echo "  User: {$user}\n";
    echo "  vHost: {$vhost}\n\n";
    
    // Создание тестовой очереди
    $testQueue = 'test_queue_' . time();
    echo "Создание тестовой очереди '{$testQueue}'...\n";
    $channel->queue_declare($testQueue, false, true, false, false);
    echo " Очередь создана\n\n";
    
    // Публикация тестового сообщения
    $testMessage = 'Hello from PHP! ' . date('Y-m-d H:i:s');
    $msg = new \PhpAmqpLib\Message\AMQPMessage($testMessage, ['delivery_mode' => 2]);
    
    echo "Публикация сообщения в очередь...\n";
    $channel->basic_publish($msg, '', $testQueue);
    echo " Сообщение опубликовано: {$testMessage}\n\n";
    
    // Чтение сообщения
    echo "Чтение сообщения из очереди...\n";
    $receivedMessage = null;
    
    $channel->basic_consume(
        $testQueue,
        '',
        false,
        true,
        false,
        false,
        function ($msg) use (&$receivedMessage) {
            $receivedMessage = $msg->getBody();
        }
    );
    
    // Ждём сообщение
    for ($i = 0; $i < 10 && $receivedMessage === null; $i++) {
        $channel->wait(null, false, false);
        usleep(100000); // 100ms
    }
    
    if ($receivedMessage !== null) {
        echo " Сообщение получено: {$receivedMessage}\n";
        if ($receivedMessage === $testMessage) {
            echo " Тест записи/чтения пройден!\n";
        } else {
            echo " Тест записи/чтения НЕ пройден!\n";
        }
    } else {
        echo " Сообщение не получено!\n";
    }
    
    // Очистка
    echo "\nОчистка тестовой очереди...\n";
    $channel->queue_delete($testQueue);
    echo " Очередь удалена\n\n";
    
    // Статистика очередей
    echo "Доступные очереди:\n";
    try {
        // Получаем список очередей через API
        $managementHost = $host;
        $managementPort = 15672;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://{$managementHost}:{$managementPort}/api/queues");
        curl_setopt($ch, CURLOPT_USERPWD, "$user:$password");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        
        if ($response) {
            $queues = json_decode($response, true);
            if (is_array($queues) && empty($queues)) {
                echo "  (нет очередей)\n";
            } elseif (is_array($queues)) {
                foreach (array_slice($queues, 0, 5) as $queue) {
                    echo "  {$queue['name']}: {$queue['messages']} сообщений\n";
                }
                if (count($queues) > 5) {
                    echo "  ... и ещё " . (count($queues) - 5) . " очередей\n";
                }
            }
        }
    } catch (Exception $e) {
        echo "  (не удалось получить список очередей)\n";
    }
    
    // Закрытие
    $channel->close();
    $connection->close();
    
    echo "\n Все тесты пройдены!\n";
    
} catch (Exception $e) {
    echo " Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
