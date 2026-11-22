-- Схема базы данных для SHOP приложения (PostgreSQL 15+)
-- Запускается первым в /docker-entrypoint-initdb.d/
-- Только DDL: CREATE TABLE, INDEX, VIEW, FUNCTION, TRIGGER. Данные загружаются отдельно в 01-* файле.

-- Включение расширений
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";

-- Таблица пользователей
CREATE TABLE IF NOT EXISTS "user" (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100),
    address TEXT,
    additional_info TEXT,
    avatar VARCHAR(255) DEFAULT NULL,
    roles JSONB NOT NULL DEFAULT '["ROLE_CUSTOMER"]'::jsonb,
    enabled BOOLEAN DEFAULT true,
    registered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    last_login_at TIMESTAMP(0) WITHOUT TIME ZONE,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE
);

CREATE INDEX IF NOT EXISTS idx_user_email ON "user"(email);
CREATE INDEX IF NOT EXISTS idx_user_username ON "user"(username);
CREATE INDEX IF NOT EXISTS idx_user_enabled ON "user"(enabled);

-- Таблица категорий
CREATE TABLE IF NOT EXISTS category (
    id SERIAL PRIMARY KEY,
    title VARCHAR(128) UNIQUE NOT NULL,
    slug VARCHAR(128) UNIQUE NOT NULL,
    image VARCHAR(255),
    parent_id INTEGER REFERENCES category(id) ON DELETE SET NULL,
    added TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    edited TIMESTAMP(0) WITHOUT TIME ZONE
);

CREATE INDEX IF NOT EXISTS idx_category_slug ON category(slug);
CREATE INDEX IF NOT EXISTS idx_category_parent ON category(parent_id);

-- Таблица продуктов
CREATE TABLE IF NOT EXISTS product (
    id SERIAL PRIMARY KEY,
    title VARCHAR(128) NOT NULL,
    slug VARCHAR(128) UNIQUE NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    description TEXT NOT NULL,
    category_id INTEGER NOT NULL REFERENCES category(id),
    available INTEGER DEFAULT 0,
    added TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    viewed INTEGER DEFAULT 0,
    last_viewed TIMESTAMP(0) WITHOUT TIME ZONE,
    edited TIMESTAMP(0) WITHOUT TIME ZONE
);

CREATE INDEX IF NOT EXISTS idx_product_slug ON product(slug);
CREATE INDEX IF NOT EXISTS idx_product_category ON product(category_id);
CREATE INDEX IF NOT EXISTS idx_product_price ON product(price);
CREATE INDEX IF NOT EXISTS idx_product_available ON product(available);
CREATE INDEX IF NOT EXISTS idx_product_added ON product(added);
CREATE INDEX IF NOT EXISTS idx_product_viewed ON product(viewed);

-- Полнотекстовый поиск по продуктам
CREATE INDEX IF NOT EXISTS idx_product_search ON product USING gin(to_tsvector('english', title || ' ' || description));

-- Таблица изображений продуктов
CREATE TABLE IF NOT EXISTS product_image (
    id SERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL REFERENCES product(id) ON DELETE CASCADE,
    path VARCHAR(255) NOT NULL,
    alt VARCHAR(255),
    position INTEGER DEFAULT 0,
    uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_product_image_product ON product_image(product_id);

-- Таблица заказов
CREATE TABLE IF NOT EXISTS "order" (
    id SERIAL PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INTEGER REFERENCES "user"(id),
    customer_name VARCHAR(255) NOT NULL,
    delivery_address TEXT NOT NULL,
    additional_info TEXT,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL DEFAULT 'new',
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE,
    paid_at TIMESTAMP(0) WITHOUT TIME ZONE,
    shipped_at TIMESTAMP(0) WITHOUT TIME ZONE
);

CREATE INDEX IF NOT EXISTS idx_order_number ON "order"(order_number);
CREATE INDEX IF NOT EXISTS idx_order_user ON "order"(user_id);
CREATE INDEX IF NOT EXISTS idx_order_status ON "order"(status);
CREATE INDEX IF NOT EXISTS idx_order_created_at ON "order"(created_at);

-- Таблица элементов заказа
CREATE TABLE IF NOT EXISTS order_item (
    id SERIAL PRIMARY KEY,
    order_id INTEGER NOT NULL REFERENCES "order"(id) ON DELETE CASCADE,
    product_id INTEGER NOT NULL REFERENCES product(id),
    quantity INTEGER NOT NULL DEFAULT 1,
    price DECIMAL(10,2) NOT NULL DEFAULT 0
);

CREATE INDEX IF NOT EXISTS idx_order_item_order ON order_item(order_id);
CREATE INDEX IF NOT EXISTS idx_order_item_product ON order_item(product_id);

-- Таблица миграций Doctrine
CREATE TABLE IF NOT EXISTS migration_versions (
    version VARCHAR(191) PRIMARY KEY,
    executed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
    execution_time INTEGER DEFAULT NULL
);

-- Таблица сообщений Messenger (doctrine transport и failed queue)
CREATE TABLE IF NOT EXISTS messenger_messages (
    id BIGSERIAL PRIMARY KEY,
    body TEXT NOT NULL,
    headers TEXT NOT NULL,
    queue_name VARCHAR(190) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
    delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL
);

CREATE INDEX IF NOT EXISTS idx_messenger_messages_queue ON messenger_messages(queue_name);
CREATE INDEX IF NOT EXISTS idx_messenger_messages_available ON messenger_messages(available_at);
CREATE INDEX IF NOT EXISTS idx_messenger_messages_delivered ON messenger_messages(delivered_at);

-- Представление для статистики продаж
CREATE OR REPLACE VIEW sales_stats AS
SELECT
    DATE_TRUNC('day', o.created_at) AS sale_date,
    COUNT(o.id) AS orders_count,
    SUM(o.total_amount) AS total_sales,
    AVG(o.total_amount) AS avg_order_value
FROM "order" o
WHERE o.status NOT IN ('cancelled')
GROUP BY DATE_TRUNC('day', o.created_at)
ORDER BY sale_date DESC;

-- Представление для статистики пользователей
CREATE OR REPLACE VIEW user_stats AS
SELECT
    DATE_TRUNC('day', registered_at) AS registration_date,
    COUNT(id) AS new_users,
    COUNT(id) FILTER (WHERE enabled = true) AS active_users
FROM "user"
GROUP BY DATE_TRUNC('day', registered_at)
ORDER BY registration_date DESC;

-- Представление для популярных товаров
CREATE OR REPLACE VIEW popular_products AS
SELECT
    p.id,
    p.title,
    p.slug,
    p.price,
    p.viewed,
    c.title AS category_title,
    c.slug AS category_slug
FROM product p
JOIN category c ON p.category_id = c.id
ORDER BY p.viewed DESC
LIMIT 100;

-- Триггер для обновления updated_at
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_user_updated_at BEFORE UPDATE ON "user"
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_category_edited BEFORE UPDATE ON category
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_product_edited BEFORE UPDATE ON product
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_order_updated_at BEFORE UPDATE ON "order"
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Начальные данные (опционально)
/*
INSERT INTO "user" (username, email, password_hash, roles, enabled) VALUES
    ('admin', 'admin@shop.local', '$argon2id$v=19$m=65536,t=3,p=4$example', '["ROLE_ADMIN"]'::jsonb, true),
    ('seller', 'seller@shop.local', '$argon2id$v=19$m=65536,t=3,p=4$example', '["ROLE_SELLER"]'::jsonb, true),
    ('customer', 'customer@shop.local', '$argon2id$v=19$m=65536,t=3,p=4$example', '["ROLE_CUSTOMER"]'::jsonb, true)
ON CONFLICT (email) DO NOTHING;

INSERT INTO category (title, slug, image) VALUES
    ('Electronics', 'electronics', null),
    ('Clothing', 'clothing', null),
    ('Books', 'books', null)
ON CONFLICT (slug) DO NOTHING;
*/