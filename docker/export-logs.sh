#!/bin/sh
# Скрипт для экспорта Docker логов в локальные файлы
# Использование: ./docker/export-logs.sh [lines]
# lines - количество строк (по умолчанию 500)

LINES=${1:-500}
LOGS_DIR="$(cd "$(dirname "$0")" && pwd)/logs"
mkdir -p "$LOGS_DIR"

# Список контейнеров
CONTAINERS="shop_nginx shop_php shop_postgres shop_redis shop_rabbitmq shop_elasticsearch shop_prometheus shop_grafana shop_jaeger"

for container in $CONTAINERS; do
    container_name="${container#shop_}"
    container_dir="$LOGS_DIR/$container_name"
    mkdir -p "$container_dir"
    
    echo "Exporting $container logs..."
    docker logs --tail "$LINES" "$container" > "$container_dir/docker.log" 2>&1
    echo "  -> $container_dir/docker.log ($(wc -l < "$container_dir/docker.log") lines)"
done

echo ""
echo "Logs exported to: $LOGS_DIR"
ls -la "$LOGS_DIR"/*/
