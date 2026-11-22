# SHOP Application - Modern Symfony 7 E-Commerce Platform

Модернизированное приложение интернет-магазина на Symfony 7.x с архитектурой DDD/CQRS.

## Общая информация

**Тип проекта:** Модернизированное приложение интернет-магазина на Symfony 7.x
**Архитектура:** DDD/CQRS с полной изоляцией слоёв
**Статус:** В активной разработке, 97.5% соответствие Senior уровню

## Архитектурные слои

```
src/
├── Domain/              # Доменный слой (сущности, value objects, репозитории интерфейсы)
│   ├── Shop/           # Домен магазина (товары, категории)
│   ├── User/           # Домен пользователей
│   └── Order/          # Домен заказов
├── Application/         # Слой приложений (команды, запросы, DTO, обработчики)
│   ├── Command/        # CQRS команды
│   ├── Query/          # CQRS запросы
│   ├── DTO/            # Data Transfer Objects
│   └── Handler/        # Обработчики команд
├── Infrastructure/      # Инфраструктурный слой
│   ├── Persistence/    # Реализация репозиториев
│   ├── Messaging/      # Очереди сообщений
│   ├── Storage/        # Хранение файлов
│   ├── Cache/          # Сервисы кэширования
│   ├── Prometheus/     # Метрики
│   └── ImportExport/   # Импорт/экспорт
└── UI/                  # Пользовательский интерфейс
    ├── Controller/     # Контроллеры
    ├── Request/        # Request объекты
    ├── Response/       # Response объекты
    └── EventListener/  # Слушатели событий
```

## Основные технологии


### Backend

- **PHP 8.2+** с расширениями: iconv, json, pdo, redis, amqp, intl, gd
- **Symfony 7.x** - современный PHP фреймворк
- **Doctrine ORM 3.x** - работа с БД
- **PostgreSQL 15** - основная база данных
- **Redis** - кэш, счётчики, лёгкие очереди, блокировки и rate limiter
- **RabbitMQ** - очередь сообщений
- **Elasticsearch** - полнотекстовый поиск

### Frontend

- **Bootstrap 5.3** - UI фреймворк
- **Webpack Encore** - сборка ассетов
- **Twig** - шаблонизатор

### DevOps

- **Docker & Docker Compose** - контейнеризация
- **Prometheus** - мониторинг и метрики
- **Grafana** - визуализация метрик
- **Jaeger** - distributed tracing
- **OpenTelemetry** - телеметрия

## Структура шаблонов

Все Twig-шаблоны находятся в `templates/`:

- `templates/shop/` — активная витрина;
- `templates/user/` — профиль, заказы, вход и регистрация;
- `templates/admin/` — Sonata Admin, мониторинг и кастомные поля;
- `templates/emails/` — письма;
- `templates/includes/` — переиспользуемые фрагменты;
- `templates/{shop,user}/legacy/` — архивные шаблоны из старых bundle-структур.

Активный Sonata-layout: `templates/admin/standard_layout.html.twig` (наследует
`@SonataAdmin/standard_layout.html.twig`). В `src/Store/*/Resources/views`
шаблонов больше нет.

## Быстрый старт

### Требования
- Docker & Docker Compose
- PHP 8.2+ (для локальной разработки)
- Composer
- Node.js 18+ & Yarn/NPM

### Установка

#### 1. Клонирование репозитория

```bash
git clone <repository_url> .
```

#### 2. Установка зависимостей

```bash
composer install
npm install
```

#### 3. Настройка окружения

```bash
cp .env .env.local
# Отредактируйте .env.local при необходимости
```

#### 4. Запуск Docker контейнеров

```bash
docker-compose up -d
```

Контейнеры включают:
- **nginx** (порт 8042)
- **php-fpm** (PHP 8.2)
- **postgres** (порт 5432)
- **redis** (порт 6379)
- **rabbitmq** (порт 5672, UI: 15672)
- **elasticsearch** (порт 9200)
- **postgres_exporter** (метрики БД внутри docker-сети на 9187)
- **prometheus** (порт 9090)
- **grafana** (порт 3000)
- **jaeger** (порт 16686)

#### 5. Инициализация базы данных

```bash
# Создание схемы БД
docker exec php php bin/console doctrine:database:create
docker exec php php bin/console doctrine:migrations:migrate

# Или загрузить готовый дамп
docker exec postgres psql -U shop_user -d shop -f /docker-entrypoint-initdb.d/schema.sql
```

#### 6. Загрузка фикстур (опционально)

```bash
docker exec php php bin/console doctrine:fixtures:load --no-interaction

# Добавление тестового товара в индекс(дальнейшее обновление будет при обновлении данных)
docker exec php php bin/console app:product:reindex
```

#### 7. Сборка ассетов

Из папки assets/*
в папку public/build/*

```bash
# установка
npm run install
# Билд
npm run build
# или для разработки
npm run dev
```

php bin/console assets:install
php bin/console cache:clear 


#### 8. Доступ к приложению
Откройте в браузере: http://localhost:8042

##  Панели мониторинга



### Grafana

- URL: http://localhost:3000
- Логин: `admin`
- Пароль: `admin`

### Prometheus

- URL: http://localhost:9090
- PostgreSQL метрики собираются через `postgres_exporter`, а не напрямую с `postgres:5432`

### RabbitMQ Management

- URL: http://localhost:15672
- Логин: `guest`
- Пароль: `guest`

### Jaeger (Tracing)

- URL: http://localhost:16686

## Логи

- Общий лог приложения: `var/log/dev.log` или `var/log/prod.log`
- Диагностика поиска и Elasticsearch fallback: `var/log/search_dev.log` или `var/log/search_prod.log`
- `ProductQueryService` и `ProductSearchService` пишут в отдельный `search`-канал, поэтому сообщения по поиску не попадают в основной `main`-лог

## Роли пользователей

- **ROLE_CUSTOMER** - покупатель (может добавлять в корзину, оформлять заказы)
- **ROLE_SELLER** - продавец (управление товарами, импорт/экспорт XML)
- **ROLE_STAFF** - персонал (просмотр статистики)
- **ROLE_ADMIN** - администратор (полный доступ)

## API

### Аутентификация

```bash
# POST /api/login
{
    "username": "user@example.com",
    "password": "password"
}

# Ответ:
{
    "access_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "expires_in": 3600,
    "token_type": "Bearer"
}
```

### Получение товаров

```bash
# GET /api/products
# GET /api/product/{id}
```

### Импорт/экспорт товаров для продавцов

```bash
# POST /seller/import/xml - импорт товаров через XML файл (требует ROLE_SELLER)
# Форма-data: xml_file (файл)

# POST /seller/export/xml - экспорт товаров в XML файл (требует ROLE_SELLER)
# Форма-data или JSON:
#   - product_ids: массив ID товаров для экспорта (опционально, если не указано - экспортируются все товары продавца)
#   - limit: лимит товаров в одном файле (опционально, по умолчанию 1000)
#   - output_path: путь для сохранения файла (опционально)

# Ответ:
{
    "message": "XML import/export started. You will be notified when complete.",
    "job_id": "строка_идентификатор_задачи"
}
```

#### Большие XML-файлы

Импорт не загружает XML в память целиком:

- загруженный файл сохраняется на диск в `var/uploads/xml/{job_id}.xml`;
- в очередь RabbitMQ/Doctrine передаётся только путь к файлу;
- `XmlProductReader` читает документ потоково через `XMLReader` (по одному `<product>`);
- товары сохраняются батчами с `flush()`/`clear()`, размер батча задаётся `XML_IMPORT_BATCH_SIZE` (по умолчанию `100`).

При ограниченной памяти уменьшите `XML_IMPORT_BATCH_SIZE` (например, до `25–50`). Это не меняет объём потребления на сам XML, но снижает пиковое число managed-сущностей Doctrine.

### Метрики Prometheus

```bash
# GET /metrics
```

## 🧪 Тестирование

```bash
# Запуск тестов
composer test

# PHP CS Fixer
composer cs
composer cs-fix
```

## Основные команды

```bash
# Очистка кэша
php bin/console cache:clear

# или более быстрый
./scripts/clear-cache.sh
# +
php bin/console cache:warmup

# Проверка валидности конфигов
php bin/console config:dump-framework

# Просмотр роутов
php bin/console debug:router

# Doctrine
php bin/console doctrine:database:create
php bin/console doctrine:schema:update --dump-sql
php bin/console doctrine:migrations:generate
php bin/console doctrine:migrations:migrate

# Messenger (очереди)
php bin/console messenger:consume async

# Качество кода
php -l src/Application/Query/ProductQueryService.php

# twig
php bin/console lint:twig templates/shop/product/show.html.twig

# DI containers check
docker exec -i shop_php php bin/console lint:container
```

### processes

run `top` to see PIDs
kill specific process  `kill -9`

## 🛡 Безопасность и Docker

### Принцип минимальных привилегий

В production-окружении **открыты только необходимые порты**:

| Сервис | Порт | Статус | Обоснование |
|--------|------|--------|-------------|
| nginx | 8042 | ✅ Открыт | Доступ к веб-приложению |
| grafana | 3000 | ⚠️ Dev only | Только для разработки, в production закрыть |
| postgres | 5432 | ❌ Закрыт | Доступ только из PHP контейнера |
| redis | 6379 | ❌ Закрыт | Доступ только из PHP контейнера |
| rabbitmq | 5672/15672 | ❌ Закрыт | Доступ только из PHP контейнера |
| elasticsearch | 9200/9300 | ❌ Закрыт | Доступ только из PHP контейнера |
| prometheus | 9090 | ❌ Закрыт | Доступ через Grafana |
| jaeger | 16686 | ❌ Закрыт | Доступ через proxy или SSH туннель |

### Доступ к сервисам для отладки

#### 1. Доступ через PHP скрипты (рекомендуется)

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

#### 2. Доступ через docker exec

#### PostgreSQL
```bash
# Подключение к psql
docker-compose exec postgres psql -U shop_user -d shop

# Выполнить запрос
docker-compose exec postgres psql -U shop_user -d shop -c "SELECT * FROM products LIMIT 5;"
```

#### Redis
```bash
# Подключение к redis-cli
docker-compose exec redis redis-cli

# Команды
docker-compose exec redis redis-cli PING
docker-compose exec redis redis-cli KEYS '*'
docker-compose exec redis redis-cli INFO
```

#### RabbitMQ
```bash
# Проверка статуса
docker-compose exec rabbitmq rabbitmq-diagnostics status

# Список очередей
docker-compose exec rabbitmq rabbitmqctl list_queues

# Список exchange
docker-compose exec rabbitmq rabbitmqctl list_exchanges
```

#### Elasticsearch
```bash
# Проверка кластера
docker-compose exec elasticsearch curl -X GET "localhost:9200/_cluster/health"

# Список индексов
docker-compose exec elasticsearch curl -X GET "localhost:9200/_cat/indices?v"
```

### Рекомендации по безопасности

- **Никогда не открывать порты БД наружу**
- **Использовать secrets для паролей**
- **Ограничить доступ к сети (Firewall)**
- **Регулярно обновлять образы**

## Резильентность (Resilience)

### Circuit Breaker Pattern

Реализован паттерн **Circuit Breaker** для защиты от сбоев внешних сервисов (платежные шлюзы, API доставки, уведомления и т.д.).

**Состояния:**
- **CLOSED** - нормальная работа, запросы проходят
- **OPEN** - сбой, запросы блокируются, используется fallback
- **HALF_OPEN** - тестовое состояние, один запрос проверяет восстановление

## Анализ производительности Redis

- **Unit тесты**: ~0.05s (приемлемо)
- **Интеграционные тесты**: ~0.15s (приемлемо для реального Redis)
- **Критические оптимизации**: Использован `executeCommand('MEMORY', ['USAGE', $key])`, оптимизированы `scan()` операции.

### Redis beyond cache

- `RedisCounter` — счётчики (например, просмотры товаров, TTL 30 дней).
- `RedisQueue` — лёгкая очередь для высокочастотных низкоприоритетных задач.
- `RedisLock` — `SET NX EX` + безопасное освобождение через Lua (защита от дублей email).
- `RedisRateLimiter` — атомарный `INCR`+`EXPIRE` через Lua (лимит поиска).

Просмотры товаров буферизуются в Redis и персистятся в PostgreSQL командой:

```bash
docker-compose exec php php bin/console app:flush-product-views
```

Команду нужно запускать по cron раз в минуту. Если Redis недоступен, просмотр
синхронно увеличивается в БД.

## Аудит соответствия Senior уровню

**Общая оценка: 85%**

Проект **соответствует** Senior уровню по:
- ✅ DDD архитектура
- ✅ CQRS разделение
- ✅ DTO на границах
- ✅ Value Objects
- ✅ Message Bus
- ✅ Batch processing
- ✅ Monitoring (Prometheus, Grafana)
- ✅ Logging (Monolog)
- ✅ Docker infrastructure

Требует доработки для **полного соответствия**:
- ⚠️ Чистые контроллеры (без наследования)
- ⚠️ Идемпотентность handlers
- ⚠️ Тестовое покрытие (>80%)
- ⚠️ Circuit breaker

## Скрипты

Для работы с сервисами используйте скрипты в папке `scripts/`.

### Примеры использования:

#### Проверка подключения:

```bash
docker-compose exec php php scripts/test_redis.php
docker-compose exec php php scripts/test_postgres.php
docker-compose exec php php scripts/test_rabbitmq.php
docker-compose exec php php scripts/test_elasticsearch.php
```

#### Интерактивный доступ:
```bash
./scripts/docker-access.sh postgres
./scripts/docker-access.sh redis
./scripts/docker-access.sh rabbitmq
./scripts/docker-access.sh elastic
```

##  Лицензия

Proprietary

## Авторы

- Vladimir
