#!/bin/sh
set -e

# Подхватываем .env для локального запуска, но не модифицируем php-fpm конфиг.
if [ -f /var/www/shop/.env ]; then
    set -a
    . /var/www/shop/.env
    set +a
fi

# Запуск PHP-FPM на переднем плане
exec php-fpm -F
