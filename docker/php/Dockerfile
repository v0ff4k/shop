# Dockerfile для PHP-FPM с необходимыми расширениями
FROM php:8.2-fpm-alpine

# Install shadow package to get 'usermod' and 'groupmod'
RUN apk add --no-cache shadow && \
    # Reassign or delete existing user/group using ID 33 (often 'xfs')
    if getent passwd 33; then deluser $(getent passwd 33 | cut -d: -f1); fi && \
    if getent group 33; then delgroup $(getent group 33 | cut -d: -f1); fi && \
    # Modify www-data to use 33:33
    usermod -u 33 www-data && \
    groupmod -g 33 www-data

# Установка системных зависимостей
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    icu-dev \
    libzip-dev \
    gd-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    postgresql-dev \
    rabbitmq-c-dev \
    linux-headers \
    make \
    nano \
    htop \
    g++ \
    supervisor \
    $PHPIZE_DEPS

# Установка PHP расширений
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    intl \
    zip \
    gd \
    exif \
    bcmath \
    sockets



RUN apk add --no-cache --virtual .grpc-build-deps \
    protobuf-dev \
    openssl-dev \
    zlib-dev

# Установка gRPC через PECL (стабильная версия 1.41.0) grpc-1.41.0
RUN pecl install grpc-1.41.0 && \
    docker-php-ext-enable grpc


# Установка Redis расширения
RUN pecl install redis \
    && docker-php-ext-enable redis

# Установка AMQP (RabbitMQ) расширения
RUN pecl install amqp \
    && docker-php-ext-enable amqp

# Очистка PECL кэша и удаление build зависимостей
RUN rm -rf /tmp/pear ~/.pearrc \
    && apk del $PHPIZE_DEPS \
    && apk del .grpc-build-deps

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Создание директорий до переключения пользователя
RUN mkdir -p /var/www/shop/var/log /var/www/shop/var/exports /var/www/shop/public/uploads \
    && mkdir -p /etc/supervisor/conf.d

# Копирование конфигурации PHP
COPY php.ini /usr/local/etc/php/conf.d/shop.ini
COPY www.conf /usr/local/etc/php-fpm.d/www.conf

# Копирование конфигурации supervisor (из контекста docker/php)
COPY supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Копирование entrypoint скрипта
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Настройка прав на директории (используем www-data UID=33)
RUN chown -R www-data:www-data /var/www/shop/var \
    && chmod -R 775 /var/www/shop/var

# Рабочая директория
WORKDIR /var/www/shop

# Переключение на пользователя www-data (UID=33, стандартный для PHP-FPM)
USER www-data

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]