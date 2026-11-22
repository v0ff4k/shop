#!/bin/bash

# Скрипт для безопасного доступа к Docker сервисам
# Использование: ./scripts/docker-access.sh [service]

set -e

CONTAINER_PREFIX="shop_"
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

show_help() {
    echo -e "${GREEN}Доступные команды:${NC}"
    echo "  ./scripts/docker-access.sh postgres    - Подключиться к PostgreSQL"
    echo "  ./scripts/docker-access.sh redis       - Подключиться к Redis CLI"
    echo "  ./scripts/docker-access.sh rabbitmq    - Показать статус RabbitMQ"
    echo "  ./scripts/docker-access.sh elastic     - Показать статус Elasticsearch"
    echo "  ./scripts/docker-access.sh jaeger      - Открыть Jaeger UI через proxy"
    echo "  ./scripts/docker-access.sh help        - Показать эту справку"
    echo ""
    echo -e "${YELLOW}Примеры:${NC}"
    echo "  ./scripts/docker-access.sh postgres query \"SELECT count(*) FROM users;\""
    echo "  ./scripts/docker-access.sh redis keys \"*\""
}

access_postgres() {
    if [ "$2" = "query" ]; then
        docker-compose exec -T postgres psql -U shop_user -d shop -c "$3"
    else
        echo -e "${GREEN}Подключение к PostgreSQL...${NC}"
        echo -e "${YELLOW}Для выхода введите: \\q${NC}"
        docker-compose exec postgres psql -U shop_user -d shop
    fi
}

access_redis() {
    if [ "$2" = "keys" ]; then
        docker-compose exec -T redis redis-cli KEYS "$3"
    elif [ "$2" = "get" ]; then
        docker-compose exec -T redis redis-cli GET "$3"
    elif [ "$2" = "info" ]; then
        docker-compose exec -T redis redis-cli INFO
    else
        echo -e "${GREEN}Подключение к Redis CLI...${NC}"
        echo -e "${YELLOW}Для выхода введите: exit${NC}"
        docker-compose exec redis redis-cli
    fi
}

access_rabbitmq() {
    echo -e "${GREEN}Статус RabbitMQ:${NC}"
    docker-compose exec rabbitmq rabbitmq-diagnostics status
    echo ""
    echo -e "${GREEN}Список очередей:${NC}"
    docker-compose exec rabbitmq rabbitmqctl list_queues name messages consumers
    echo ""
    echo -e "${GREEN}Список Exchange:${NC}"
    docker-compose exec rabbitmq rabbitmqctl list_exchanges name type
}

access_elastic() {
    echo -e "${GREEN}Статус Elasticsearch:${NC}"
    docker-compose exec elasticsearch curl -s http://localhost:9200/_cluster/health | jq .
    echo ""
    echo -e "${GREEN}Список индексов:${NC}"
    docker-compose exec elasticsearch curl -s http://localhost:9200/_cat/indices?v
}

access_jaeger() {
    echo -e "${YELLOW}Jaeger UI доступен только внутри Docker сети${NC}"
    echo -e "${GREEN}Для доступа используйте один из способов:${NC}"
    echo ""
    echo "1. Через nginx proxy (рекомендуется):"
    echo "   Настройте location /jaeger/ в nginx.conf"
    echo ""
    echo "2. Через SSH туннель:"
    echo "   ssh -L 16686:localhost:16686 user@server"
    echo "   Затем откройте http://localhost:16686"
    echo ""
    echo "3. Через docker exec с пробросом порта:"
    echo "   docker run --rm -it --network=shop_shop_network -p 16686:16686 alpine socat TCP-LISTEN:16686,fork TCP:jaeger:16686"
}

# Main script
case "$1" in
    postgres|pg)
        access_postgres "$@"
        ;;
    redis)
        access_redis "$@"
        ;;
    rabbitmq|rmq)
        access_rabbitmq "$@"
        ;;
    elastic|es)
        access_elastic "$@"
        ;;
    jaeger)
        access_jaeger
        ;;
    help|--help|-h)
        show_help
        ;;
    *)
        echo -e "${RED}Ошибка: Неизвестная команда '$1'${NC}"
        echo ""
        show_help
        exit 1
        ;;
esac
