-- Обновленный дамп данных для PostgreSQL
-- Содержит актуальные пути к файлам с хэш-директориями
-- Сгенерировано: 2026-04-10 после миграции файлов

-- ==========================================
-- ПОЛЬЗОВАТЕЛИ
-- ==========================================
-- Пароли: admin/admin123, user/user123, guest/guest123

INSERT INTO "user" (id, username, email, password_hash, full_name, address, additional_info, roles, enabled, registered_at, last_login_at, updated_at)
OVERRIDING SYSTEM VALUE
VALUES
    (1, 'admin', 'admin@example.com', '$argon2id$v=19$m=65536,t=4,p=1$ZUJIVUhpVnM0Ym5tZUlwUg$xSxrmLMpUwui6V1ZUad8kNreD+jJqk5C1rPzDgGnDn4', 'Admin User', NULL, NULL, '["ROLE_ADMIN"]', true, '2026-04-06 02:28:04', NULL, NULL),
    (2, 'user', 'user@example.com', '$argon2id$v=19$m=65536,t=4,p=1$cHNsUU9xLlJXcDAvYnQzRQ$JLJakhDf79xmdvGj/xaMpBoQ7SaOrbYsVwFAs5D0fe8', 'Test User', NULL, NULL, '["ROLE_CUSTOMER"]', true, '2026-04-06 02:28:04', NULL, NULL),
    (3, 'guest', 'guest@example.com', '$argon2id$v=19$m=65536,t=4,p=1$Si9LSHRsd0phTk5Tc3Nqag$NE9yOjAbGhTlVrocgz0Ai3kTUeCRmnQJLNbpKSdHB+Q', 'Guest User', NULL, NULL, '["ROLE_CUSTOMER"]', true, '2026-04-06 02:28:04', NULL, NULL);

SELECT setval(pg_get_serial_sequence('"user"', 'id'), 3);

-- ==========================================
-- КАТЕГОРИИ (с актуальными хэш-путями)
-- ==========================================

INSERT INTO category (id, title, slug, image, parent_id, added, edited)
OVERRIDING SYSTEM VALUE
VALUES
    (1, 'Europian gold', 'europian-gold', 'categories/dc/90/dc90b4e62e7c6478652ca66ae74709c8f47657e557a7b3f927cc2f38e687a918/947b4a6863b61a9b4f12c91d94b86171.jpg', NULL, '2016-12-21 17:46:00', '2016-12-21 00:48:00'),
    (2, 'Russian', 'russian', 'categories/4b/56/4b562b89632767b7095c8e5836607d0fc241c5c2f674020a6705f18b26347208/a991dca7c8fea8b4f84ac75696714e13.png', NULL, '2016-12-21 17:50:00', '2016-12-21 17:51:00'),
    (3, 'Pellets', 'pellets', 'categories/2d/de/2dde5dcb984a17f57b92ac1816c866f9a2bad6fa8b3a67e5138e8bf4e23e1b4f/6a63657822582052787dd4f60871739b.png', NULL, '2016-12-21 17:51:00', '2011-01-01 00:00:00'),
    (4, '8bit', '8bit', 'categories/3e/55/3e55153b60496bf7ac1f58d8320b7be677dd02328e620e01541d90d5b15eaa21/1eb599b1011901747dbe8b1f5b451615.png', NULL, '2016-12-21 17:52:00', '2011-01-01 00:00:00'),
    (5, 'Stone(nugget)', 'stone-nugget', 'categories/9a/c8/9ac8b73988a8308599fe0a07c4fdcb5be6716b48bd523a9270719433e907d99e/334e0b12c1e280c2b793dabd07182406.png', NULL, '2016-12-21 18:03:00', '2016-12-01 18:04:00'),
    (6, 'Coins', 'coins', 'categories/75/ee/75eef8a975aa6890264b067aa265397d3e8e6d121ead1ebacd56c680f81b2bc0/82b19c1fe50a759e500f50f1fa71a586.png', NULL, '2016-12-21 18:04:00', '2011-01-01 00:00:00');

SELECT setval(pg_get_serial_sequence('category', 'id'), 6);

-- ==========================================
-- ПРОДУКТЫ
-- ==========================================

INSERT INTO product (id, title, slug, price, description, category_id, available, added, viewed, last_viewed, edited)
OVERRIDING SYSTEM VALUE
VALUES
    (1, '10 g ru gold', '10-g-ru-gold', 13.50, 'awesome 10 g gold from Russia with love', 2, 9, '2016-12-22 09:01:00', 16, '2017-01-05 12:56:27', '2026-04-08 17:09:44'),
    (2, 'random granules', 'random-granules', 1.13, 'Perfect, clear 1$ per gram, 1,5 kg available !!!', 3, 1500, '2016-12-22 09:34:00', 4, '2017-01-05 12:56:20', '2026-04-08 17:09:44'),
    (3, 'EU fine 1kg ingot of gold', 'eu-fine-1kg-ingot-of-gold', 1299.95, 'Perfect  1 kg ingots.', 1, 4, '2016-12-22 10:00:00', 424, '2017-01-11 21:16:04', '2026-04-08 17:09:44'),
    (4, 'a lot of gold coins', 'a-lot-of-gold-coins', 1.32, 'A lot of gold coins 1.32$ per coins', 6, 1236, '2016-12-22 10:25:00', 201, '2017-01-11 20:18:02', '2026-04-08 17:09:44'),
    (5, 'Gold nuggets', 'gold-nuggets', 349.00, '400g of gold nuggets fonded in the middle of nowhere.', 5, 15, '2016-12-22 10:30:00', 87, '2017-01-11 19:53:25', '2026-04-08 17:09:44'),
    (6, '8bit ingots', '8bit-ingots', 1.30, 'e-gold for on-line games', 4, 78545, '2016-12-22 10:35:00', 140, '2017-01-11 14:50:46', '2026-04-08 17:09:44'),
    (7, '8bit new1', '8bit-new1', 1.22, '8bit new1 desc', 4, 4, '2017-01-10 19:03:00', 4, '2017-01-11 21:15:36', '2026-04-08 17:09:44'),
    (8, '8bit new2', '8bit-new2', 0.66, '8bit new2 desc', 4, 2, '2017-01-10 19:04:00', 7, '2017-01-11 21:15:49', '2026-04-08 17:09:44'),
    (9, '8bit new3', '8bit-new3', 3.56, '8bit new3 desc', 4, 1, '2017-01-10 19:04:00', 3, '2017-01-11 18:49:11', '2026-04-08 17:09:44'),
    (10, '8bit new4', '8bit-new4', 2.46, '8bit new4 desc', 4, 6, '2017-01-10 19:04:00', 4, '2012-01-01 00:00:00', '2026-04-08 17:09:44'),
    (11, '8bit new5', '8bit-new5', 5.12, '8bit new5 desc', 4, 3, '2017-01-10 19:05:00', 5, '2012-01-01 00:00:00', '2026-04-08 17:09:44'),
    (12, '8bit new6', '8bit-new6', 3.66, '8bit new6 desc', 4, 9, '2017-01-10 19:05:00', 4, '2012-01-01 00:00:00', '2026-04-08 17:09:44'),
    (13, '8bit new7', '8bit-new7', 3.71, '8bit new7 desc', 4, 9, '2017-01-10 19:06:00', 6, '2017-01-12 08:20:44', '2026-04-08 17:09:44'),
    (14, '8bit new8', '8bit-new8', 3.80, '8bit new8 desc', 4, 3, '2017-01-10 19:07:00', 31, '2017-01-11 20:59:05', '2026-04-08 17:09:44'),
    (15, 'plastic e-gold', 'plastic-e-gold', 2.15, 'Pairs of like plastic,  ingots for on-line e-gold', 4, 0, '2016-12-22 10:50:00', 200, '2016-12-22 10:53:00', '2026-04-08 17:09:44');

SELECT setval(pg_get_serial_sequence('product', 'id'), 15);

-- ==========================================
-- ИЗОБРАЖЕНИЯ ПРОДУКТОВ (с актуальными хэш-путями)
-- ==========================================

INSERT INTO product_image (id, product_id, path, alt, position, uploaded_at)
OVERRIDING SYSTEM VALUE
VALUES
    (1, 1, 'products/be/cf/becfcf51fe1b89c6df435ef7c0ed9fa0afb7a89527c0b600f9c84ec344e23875/2c00556d47ae2cf2ce4164d6774ef715.png', 'Russian gold 10g', 0, '2026-04-08 17:09:44'),
    (2, 1, 'products/4c/da/4cda38081dbababd744ca23369c4a80c4d61f81e2f65f1e03020964127cc4f1b/aa37ab030073c456e87787b86d97ce20.jpg', 'Russian gold 10g alt', 1, '2026-04-08 17:09:44'),
    (3, 2, 'products/67/ea/67ea0611b7b51bdcb504f940e6d64d95b7459bfa45d5c9c2a656d03c8b063701/543abd9ecc79111f1188ffbad1d8e459.jpg', 'Granules 2', 0, '2026-04-08 17:09:44'),
    (4, 2, 'products/f1/28/f128b719053c2f9e6eb6525ca4c61d36f878f064ffd5dd6a690144fa172fb5ca/e90e9c8cb6eeeec71ed59a79ea0fdaf5.jpg', 'Granules 3', 1, '2026-04-08 17:09:44'),
    (5, 2, 'products/1e/b1/1eb167336d1d0e47119afd530bcb5d6e15bc6b8341f41346af965160998e52d9/607831ad7f167a2be9b79b5695531268.jpg', 'Granules 4', 2, '2026-04-08 17:09:44'),
    (6, 2, 'products/ec/94/ec94ba03839252514e167166fc13f1dc77a7bac0737ca9d1aa6c22561155d452/433a56df1472d9ffe0a5eb4c2829f9b6.jpg', 'Granules 5', 3, '2026-04-08 17:09:44'),
    (7, 3, 'products/31/77/31779f52fc82a5fbf32a3c047d3f09d30651d7b5f8655875640304b39a85eb66/6de5075b3fd28ca25cc682fe8fb356f9.png', 'EU fine gold 1kg', 0, '2026-04-08 17:09:44'),
    (8, 3, 'products/c4/2c/c42cd03c193a20bb0b08e4db6db26301eb65e3caf693f0696eadb134faa44eed/0d75d127e6b8588c5c6c87e181de62c3.png', 'EU fine gold 1kg alt', 1, '2026-04-08 17:09:44'),
    (9, 4, 'products/b7/4e/b74ee758905c6674b2be8409d533f037e6def187e258c74f6ecfb3065e5405da/7938fae599679d32f2431d0dfd6d827a.jpg', 'Gold coins 1', 0, '2026-04-08 17:09:44'),
    (10, 4, 'products/3d/51/3d51aaa3fc0eaadca38f1c4ed4bfd9d9d333ca2036d283dc057dd1659a2f73d5/9c561713ef3733cd3e372b3782754516.png', 'Gold coins 2', 1, '2026-04-08 17:09:44'),
    (11, 5, 'products/9a/c8/9ac8b73988a8308599fe0a07c4fdcb5be6716b48bd523a9270719433e907d99e/83cb26eb2fbba199e9ba71b6ec00bc20.png', 'Gold nugget 1', 0, '2026-04-08 17:09:44'),
    (12, 5, 'products/96/95/9695224a513eb8ef33f8162899b633c4dbb29a25df803fab979a972dadd34bd2/8f1096a515ea223101703699bf409944.png', 'Gold nugget 2', 1, '2026-04-08 17:09:44'),
    (13, 6, 'products/94/66/94660727f2c47b3df0ca7026b9f51605c6155d13d878dff70a3724a3ce19de78/3f453a41850bc53d1db9c70b0b50e682.png', '8bit gold 800g', 0, '2026-04-08 17:09:44'),
    (14, 7, 'products/5e/c4/5ec483665f8651e184b652d13591fbb5d45669bb80896eabc781d59d0ae320f6/9227d8bb80cc7703de335fc7149700e7.png', '8bit plastic 500g', 0, '2026-04-08 17:09:44');

SELECT setval(pg_get_serial_sequence('product_image', 'id'), 14);
