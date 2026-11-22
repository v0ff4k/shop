<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Начальная миграция для создания схемы БД.
 */
final class Version20260325000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema creation for SHOP application';
    }

    public function up(Schema $schema): void
    {
        // Таблица пользователей
        $this->addSql('
            CREATE TABLE IF NOT EXISTS "user" (
                id SERIAL PRIMARY KEY,
                username VARCHAR(100) UNIQUE NOT NULL,
                email VARCHAR(255) UNIQUE NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                full_name VARCHAR(100),
                address TEXT,
                additional_info TEXT,
                roles JSONB NOT NULL DEFAULT \'["ROLE_CUSTOMER"]\'::jsonb,
                enabled BOOLEAN DEFAULT true,
                registered_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
                last_login_at TIMESTAMP(0) WITHOUT TIME ZONE,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE
            )
        ');

        // Таблица категорий
        $this->addSql('
            CREATE TABLE IF NOT EXISTS category (
                id SERIAL PRIMARY KEY,
                title VARCHAR(128) UNIQUE NOT NULL,
                slug VARCHAR(128) UNIQUE NOT NULL,
                image VARCHAR(255),
                parent_id INTEGER REFERENCES category(id) ON DELETE SET NULL,
                added TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
                edited TIMESTAMP(0) WITHOUT TIME ZONE
            )
        ');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_category_slug ON category(slug)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_category_parent ON category(parent_id)');

        // Таблица продуктов
        $this->addSql('
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
            )
        ');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_product_slug ON product(slug)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_product_category ON product(category_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_product_price ON product(price)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_product_added ON product(added)');

        // Таблица изображений продуктов
        $this->addSql('
            CREATE TABLE IF NOT EXISTS product_image (
                id SERIAL PRIMARY KEY,
                product_id INTEGER NOT NULL REFERENCES product(id) ON DELETE CASCADE,
                path VARCHAR(255) NOT NULL,
                alt VARCHAR(255),
                position INTEGER DEFAULT 0,
                uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW()
            )
        ');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_product_image_product ON product_image(product_id)');

        // Таблица заказов
        $this->addSql('
            CREATE TABLE IF NOT EXISTS "order" (
                id SERIAL PRIMARY KEY,
                order_number VARCHAR(50) UNIQUE NOT NULL,
                user_id INTEGER REFERENCES "user"(id),
                customer_name VARCHAR(255) NOT NULL,
                delivery_address TEXT NOT NULL,
                additional_info TEXT,
                total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                status VARCHAR(50) NOT NULL DEFAULT \'new\',
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE,
                paid_at TIMESTAMP(0) WITHOUT TIME ZONE,
                shipped_at TIMESTAMP(0) WITHOUT TIME ZONE
            )
        ');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_order_number ON "order"(order_number)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_order_user ON "order"(user_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_order_status ON "order"(status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_order_created_at ON "order"(created_at)');

        // Таблица элементов заказа
        $this->addSql('
            CREATE TABLE IF NOT EXISTS order_item (
                id SERIAL PRIMARY KEY,
                order_id INTEGER NOT NULL REFERENCES "order"(id) ON DELETE CASCADE,
                product_id INTEGER NOT NULL REFERENCES product(id),
                quantity INTEGER NOT NULL DEFAULT 1,
                price DECIMAL(10,2) NOT NULL DEFAULT 0
            )
        ');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_order_item_order ON order_item(order_id)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_order_item_product ON order_item(product_id)');

        // Таблица миграций
        $this->addSql('
            CREATE TABLE IF NOT EXISTS migration_versions (
                version VARCHAR(191) PRIMARY KEY,
                executed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                execution_time INTEGER DEFAULT NULL
            )
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS order_item');
        $this->addSql('DROP TABLE IF EXISTS "order"');
        $this->addSql('DROP TABLE IF EXISTS product_image');
        $this->addSql('DROP TABLE IF EXISTS product');
        $this->addSql('DROP TABLE IF EXISTS category');
        $this->addSql('DROP TABLE IF EXISTS "user"');
        $this->addSql('DROP TABLE IF EXISTS migration_versions');
    }

    public function isTransactional(): bool
    {
        return true;
    }
}
