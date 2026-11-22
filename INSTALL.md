# Инструкция по запуску проекта SHOP

## Быстрый старт с Docker

### 1. Запуск контейнеров
```bash
docker-compose up -d
```

Проверьте статус:
```bash
docker-compose ps
```
Все контейнеры (`nginx`, `php`, `postgres`, `redis`, `rabbitmq`, `elasticsearch`) должны быть в статусе `Up`.

### 2. Установка зависимостей (если не установлены)
```bash
composer install
npm install
```

Если `composer install` ругается на `ext-grpc` — это ожидаемо. Проект использует
OTLP over HTTP/protobuf (порт 4318), а не gRPC (порт 4317). Расширение gRPC не требуется(!).

### 3. Настройка базы данных

Есть два способа настроить БД:

#### Способ A: Автоматическая загрузка через initdb (рекомендуется для fresh-старта)

Если вы делаете **полный сброс** (ниже), PostgreSQL автоматически выполнит файлы из
`databases/` при первой инициализации:

```bash
docker-compose down -v          # удалить volumes (включая postgres_data)
docker-compose up -d            # заново запустить все контейнеры
```

Файлы в `databases/` выполняются в алфавитном порядке:
1. `00-schema.sql` — DDL: CREATE TABLE, INDEX, VIEW, TRIGGER (без INSERTов)
2. `01-superstore_postgres.sql` — тестовые данные (пользователи, категории, товары, изображения)

Файлы в `databases/legacy/` (старые MySQL-дампы) **не загружаются** автоматически.

#### Способ B: Ручная установка (если БД уже создана)

```bash
# Создание БД и миграция
docker-compose exec php php bin/console doctrine:database:create --if-not-exists
docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction

# Затем загрузить данные вручную
docker-compose exec postgres psql -U shop_user -d shop -f /docker-entrypoint-initdb.d/00-schema.sql
docker-compose exec postgres psql -U shop_user -d shop -f /docker-entrypoint-initdb.d/01-superstore_postgres.sql
```

### 4. Проверка подключения к инфраструктурным сервисам

```bash
# PostgreSQL
docker-compose exec postgres pg_isready -U shop_user -d shop

# Redis
docker-compose exec redis redis-cli ping

# RabbitMQ
docker-compose exec rabbitmq rabbitmq-diagnostics ping

# Elasticsearch
curl http://localhost:29200/_cluster/health
```

### 5. Сборка ассетов + добавление в индекс Эластики.
```bash
docker-compose exec php npm run build
docker-compose exec php php bin/console app:product:reindex
```

### 6. Очистка кэша Symfony (если есть проблемы)
```bash
docker-compose exec php php bin/console cache:clear
```

### 7. Доступ к приложению
- **Магазин**: http://localhost:8042
- **Grafana**: http://localhost:23000 (admin/admin)
- **Prometheus**: http://localhost:29090
- **RabbitMQ**: http://localhost:12672 (guest/guest)
- **Jaeger UI**: http://localhost:26686
- **Elasticsearch**: http://localhost:29200

## Тестовые учетные данные

После загрузки данных из `databases/01-superstore_postgres.sql`:

| Роль | Email | Пароль |
|------|-------|--------|
| Admin | admin@example.com | adminpass |
| Seller | seller@example.com | sellerpass |
| Customer | guest | guestpass! |
| Customer | user | userpass |

> **Примечание:** Пароли в дампе являются хэшами argon2id. Используйте
> `admin/adminpass` для входа в админ-панель.

## Команды для разработки

```bash
# Очистка кэша
docker-compose exec php php bin/console cache:clear
# Или внешний:  
scripts/clear-cache.sh

# Запуск тестов (PHPUnit 10)
docker-compose exec php composer test
# или
docker-compose exec php php bin/phpunit

# Юнит/интеграционные наборы отдельно
docker-compose exec php composer test:unit
docker-compose exec php composer test:integration

# Просмотр логов
docker-compose logs -f php
docker-compose logs -f nginx

# Остановка контейнеров
docker-compose down

# Полная пересборка (с очисткой volumes)
docker-compose down -v
docker-compose up -d --build

# Проверка синтаксиса PHP
docker-compose exec php php -l src/path/to/file.php
```

## Возможные проблемы и решения

### Ошибка подключения к БД
```bash
docker-compose restart postgres
docker-compose exec php php bin/console cache:clear
```

### Ошибка сборки ассетов
```bash
docker-compose exec php npm install
docker-compose exec php npm run build
```

### Ошибка прав доступа к файлам
```bash
chmod -R 777 var/ public/uploads/
```

### Сброс базы данных и перезапуск с чистого листа

```bash
docker-compose exec postgres psql -U shop_user -d shop -c "DROP SCHEMA public CASCADE; CREATE SCHEMA public;"
docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction

docker-compose down -v
docker-compose up -d --build
# Данные загрузятся автоматически через initdb
```

### Ошибка "Invalid endpoint url" в OpenTelemetry
Если видите ошибку вроде `Invalid endpoint url "http://shop_jaeger:4318/..."`:
- Проверьте, что `OTEL_EXPORTER_OTLP_ENDPOINT` использует имя сервиса `jaeger` (без underscore),
  а не `container_name` `shop_jaeger`
- Порт должен быть **4318** (OTLP HTTP), а не 4317 (gRPC)

### Трассировка запросов в Jaeger
Трассировка работает через OTLP HTTP/protobuf на порту 4318.
В `docker-compose.yml` Jaeger слушает на 4318 внутри сети.
Для внешнего доступа к OTLP добавьте порт в раздел `ports`:
```yaml
jaeger:
    ports:
        - "4318:4318"  # OTLP HTTP (опционально, для внешнего доступа)
```

## API Endpoints

### Аутентификация
```bash
curl -X POST http://localhost:8042/api/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin@example.com","password":"adminpass"}'
```

### Получение товаров
```bash
curl http://localhost:8042/api/products
curl http://localhost:8042/api/product/1
```

### Метрики
```bash
curl http://localhost:8042/metrics
```
