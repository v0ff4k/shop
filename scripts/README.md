# Scripts для работы с Docker сервисами

## Обзор

В этой папке находятся скрипты для безопасной работы с Docker сервисами **изнутри контейнера PHP**.

---

## Быстрый старт

### 1. Проверка подключения к сервисам

```bash
# Redis
docker-compose exec php php scripts/test_redis.php

# PostgreSQL
docker-compose exec php php scripts/test_postgres.php

# RabbitMQ
docker-compose exec php php scripts/test_rabbitmq.php

# Elasticsearch
docker-compose exec php php scripts/test_elasticsearch.php
```

### 2. Интерактивный доступ через bash скрипт

```bash
# PostgreSQL CLI
./scripts/docker-access.sh postgres

# Redis CLI
./scripts/docker-access.sh redis

# RabbitMQ статус
./scripts/docker-access.sh rabbitmq

# Elasticsearch статус
./scripts/docker-access.sh elastic
```

---

## Скрипты

### test_redis.php

Проверяет подключение к Redis, записывает/читает тестовый ключ, показывает статистику.

**Использование:**
```bash
docker-compose exec php php scripts/test_redis.php
```

**Выход:**
```
=== Тест подключения к Redis ===

Подключение к redis:6379...
✅ Успешное подключение!

PING: PONG

Тест записи/чтения:
Записано: test:1234567890 = Hello from PHP!
Прочитано: test:1234567890 = Hello from PHP!
✅ Тест записи/чтения пройден!
...
```

---

### test_postgres.php

Проверяет подключение к PostgreSQL, показывает таблицы, количество записей, размер БД.

**Использование:**
```bash
docker-compose exec php php scripts/test_postgres.php
```

**Выход:**
```
=== Тест подключения к PostgreSQL ===

Подключение к postgres:5432/shop...
✅ Успешное подключение!

Информация о PostgreSQL:
  Version: PostgreSQL 15.x...

Таблицы в базе данных:
  public.products
  public.users
  ...
```

---

### test_rabbitmq.php

Проверяет подключение к RabbitMQ, создаёт тестовую очередь, публикует/читает сообщение.

**Использование:**
```bash
docker-compose exec php php scripts/test_rabbitmq.php
```

**Требования:**
- Установлена библиотека `php-amqplib/php-amqplib`

**Выход:**
```
=== Тест подключения к RabbitMQ ===

Подключение к rabbitmq:5672...
✅ Успешное подключение!

Создание тестовой очереди 'test_queue_1234567890'...
✅ Очередь создана

Публикация сообщения в очередь...
✅ Сообщение опубликовано: Hello from PHP!
...
```

---

### test_elasticsearch.php

Проверяет подключение к Elasticsearch, показывает статус кластера, индексы, выполняет тестовый поиск.

**Использование:**
```bash
docker-compose exec php php scripts/test_elasticsearch.php
```

**Требования:**
- Установлена библиотека `elasticsearch/elasticsearch`

**Выход:**
```
=== Тест подключения к Elasticsearch ===

Подключение к elasticsearch:9200...
✅ Успешное подключение!

Информация о Elasticsearch:
  Name: node-1
  Cluster: docker-cluster
  Version: 8.11.0

Статус кластера:
  Status: green
  Nodes: 1
  ...
```

---

### docker-access.sh

Bash-скрипт для интерактивного доступа к сервисам.

**Использование:**
```bash
# PostgreSQL CLI
./scripts/docker-access.sh postgres

# PostgreSQL query
./scripts/docker-access.sh postgres query "SELECT count(*) FROM users;"

# Redis CLI
./scripts/docker-access.sh redis

# Redis keys
./scripts/docker-access.sh redis keys "*"

# Redis get
./scripts/docker-access.sh redis get "mykey"

# RabbitMQ статус
./scripts/docker-access.sh rabbitmq

# Elasticsearch статус
./scripts/docker-access.sh elastic

# Jaeger доступ
./scripts/docker-access.sh jaeger
```

---

## Переменные окружения

Скрипты используют следующие переменные окружения (значения по умолчанию):

| Переменная | По умолчанию | Описание |
|------------|--------------|----------|
| `REDIS_HOST` | `redis` | Хост Redis |
| `REDIS_PORT` | `6379` | Порт Redis |
| `POSTGRES_HOST` | `postgres` | Хост PostgreSQL |
| `POSTGRES_PORT` | `5432` | Порт PostgreSQL |
| `POSTGRES_DB` | `shop` | Имя БД |
| `POSTGRES_USER` | `shop_user` | Пользователь БД |
| `POSTGRES_PASSWORD` | `shop_password` | Пароль БД |
| `RABBITMQ_HOST` | `rabbitmq` | Хост RabbitMQ |
| `RABBITMQ_PORT` | `5672` | Порт RabbitMQ |
| `RABBITMQ_USER` | `guest` | Пользователь RabbitMQ |
| `RABBITMQ_PASSWORD` | `guest` | Пароль RabbitMQ |
| `ELASTICSEARCH_HOST` | `elasticsearch` | Хост Elasticsearch |
| `ELASTICSEARCH_PORT` | `9200` | Порт Elasticsearch |

---

## Безопасность

### Почему скрипты запускаются из контейнера PHP?

1. **Изоляция сети** - сервисы не открывают порты наружу
2. **Безопасность** - нет доступа с хост-машины
3. **Удобство** - не нужно настраивать проброс портов

### Как это работает?

```
┌─────────────────┐
│   Host Machine  │
│                 │
│  docker-compose │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────────────┐
│         Docker Network              │
│                                     │
│  ┌──────────┐    ┌───────────────┐ │
│  │   nginx  │    │     php       │ │
│  │  :80     │    │  :9000        │ │
│  └──────────┘    └───────┬───────┘ │
│                          │         │
│         ┌────────────────┼─────────┐
│         │                │         │
│         ▼                ▼         │
│  ┌──────────┐    ┌──────────┐     │
│  │ postgres │    │  redis   │     │
│  │  (5432)  │    │  (6379)  │     │
│  └──────────┘    └──────────┘     │
│                                    │
└────────────────────────────────────┘
```

Скрипты запускаются **внутри контейнера PHP**, который имеет доступ ко всем сервисам через Docker сеть.

---

## Troubleshooting

### "Connection refused"

1. Убедитесь, что сервисы запущены:
   ```bash
   docker-compose ps
   ```

2. Проверьте логи сервиса:
   ```bash
   docker-compose logs redis
   ```

3. Подождите запуска сервиса (особенно для Elasticsearch):
   ```bash
   docker-compose up -d
   sleep 30
   ```

### "Class not found"

Установите зависимости:
```bash
docker-compose exec php composer require php-amqplib/php-amqplib
docker-compose exec php composer require elasticsearch/elasticsearch
```

### "Access denied"

Проверьте переменные окружения в `.env`:
```bash
docker-compose exec php env | grep POSTGRES
```

---

## Добавление своих скриптов

Создайте новый скрипт по аналогии:

```php
<?php
// scripts/test_yourservice.php

echo "=== Тест подключения к YourService ===\n\n";

try {
    $host = getenv('YOURSERVICE_HOST') ?: 'yourservice';
    $port = getenv('YOURSERVICE_PORT') ?: 1234;
    
    // Ваше подключение и тесты...
    
    echo "✅ Все тесты пройдены!\n";
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "\n";
    exit(1);
}
```

---

## Ссылки

- [DOCKER_SECURITY.md](./DOCKER_SECURITY.md) - Политика безопасности
- [docker-compose.yml](../docker-compose.yml) - Конфигурация Docker
