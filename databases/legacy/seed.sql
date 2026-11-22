-- Тестовые данные для PostgreSQL
-- Пользователь: user / password: password

INSERT INTO "user" (username, password_hash, email, full_name, address, roles, enabled, registered_at)
VALUES
    ('user', '$2y$13$1rzmdvxduijow4sgksoo4uvSvO7co9qKprCJKgXu02eiq3jPb1XTu', 'u@ser.com', 'Test User', 'Test Address', '{ROLE_CUSTOMER}', true, NOW()),
    ('admin', '$2y$13$nvrvt2gezqos0wggo4sooe0xojcQwHhmmZ/9YFTGS1rB9j3OlEzPm', 'admin@server.com', 'Admin User', 'Admin Address', '{ROLE_ADMIN,ROLE_CUSTOMER}', true, NOW());

-- Категории
INSERT INTO category (title, image, added, edited)
VALUES
    ('Electronics', 'electronics.jpg', NOW(), NOW()),
    ('Books', 'books.jpg', NOW(), NOW()),
    ('Clothing', 'clothing.jpg', NOW(), NOW());

-- Продукты
INSERT INTO product (title, slug, description, price, available, category_id, added, edited, viewed)
VALUES
    ('Laptop Pro 15', 'laptop-pro-15', 'Powerful laptop for professionals', 1299.99, 10, 1, NOW(), NOW(), 0),
    ('PHP Handbook', 'php-handbook', 'Complete guide to PHP 8', 49.99, 50, 2, NOW(), NOW(), 0),
    ('Cotton T-Shirt', 'cotton-tshirt', 'Comfortable cotton t-shirt', 19.99, 100, 3, NOW(), NOW(), 0);
