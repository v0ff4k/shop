#!/bin/bash
# Скрипт очистки кэша Symfony приложения(вне докера)
# Использование: ./scripts/clear-cache.sh

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

echo "🧹 Очистка кэша Symfony приложения..."
echo "🧹SCRIPT_DIR - ${SCRIPT_DIR}"
echo "🧹PROJECT_DIR - ${PROJECT_DIR}"

# 1. Очистка кэша на хосте
echo " Очистка кэша(${PROJECT_DIR}) на хосте..."
rm -rf "${PROJECT_DIR}/var/cache"/*
echo " Кэш на хосте очищен"

# 2. Очистка кэша в Docker контейнере
echo " Очистка кэша в Docker контейнере..."
if docker ps --format '{{.Names}}' | grep -q 'shop_php'; then
    docker exec shop_php rm -rf ${PROJECT_DIR}/var/cache/*
    echo " Кэш в контейнере очищен"
else
    echo "️  Контейнер shop_php не запущен, пропускаю"
fi

# 3. Очистка кэша Symfony console (если контейнер запущен)
echo " Выполнение в shop_php(docker) symfony console cache:clear..."
if docker ps --format '{{.Names}}' | grep -q 'shop_php'; then
    docker exec -w ${PROJECT_DIR} shop_php php bin/console cache:clear --no-warmup 2>/dev/null || true
    echo " Symfony cache:clear выполнен"
fi

echo ""
echo " Кэш успешно очищен!"
