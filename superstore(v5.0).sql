-- phpMyAdmin SQL Dump
-- version 4.5.4.1deb2ubuntu2
-- http://www.phpmyadmin.net
--
-- Хост: localhost
-- Время создания: Янв 19 2017 г., 10:37
-- Версия сервера: 5.7.16-0ubuntu0.16.04.1
-- Версия PHP: 5.6.29-1+deb.sury.org~xenial+1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `superstore`
--

-- --------------------------------------------------------

--
-- Структура таблицы `category`
--

CREATE TABLE `category` (
  `id` int(11) NOT NULL,
  `title` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `slug` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `image` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `added` datetime NOT NULL,
  `edited` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Дамп данных таблицы `category`
--

INSERT INTO `category` (`id`, `title`, `slug`, `image`, `added`, `edited`) VALUES
(1, 'Europian gold', 'europian-gold', 'fine.500g.jpg', '2016-12-21 17:46:00', '2016-12-21 00:48:00'),
(2, 'Russian', 'russian', 'ru.100g.png', '2016-12-21 17:50:00', '2016-12-21 17:51:00'),
(3, 'Pellets', 'pellets', 'granules1.png', '2016-12-21 17:51:00', '2011-01-01 00:00:00'),
(4, '8bit', '8bit', '8bit2.800g.png', '2016-12-21 17:52:00', '2011-01-01 00:00:00'),
(5, 'Stone(nugget)', 'stone-nugget', 'nugget.png', '2016-12-21 18:03:00', '2016-12-01 18:04:00'),
(6, 'Coins', 'coins', 'coin1.png', '2016-12-21 18:04:00', '2011-01-01 00:00:00');

-- --------------------------------------------------------

--
-- Структура таблицы `customer`
--

CREATE TABLE `customer` (
  `id` int(11) NOT NULL,
  `username` varchar(180) COLLATE utf8_unicode_ci NOT NULL,
  `username_canonical` varchar(180) COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(180) COLLATE utf8_unicode_ci NOT NULL,
  `email_canonical` varchar(180) COLLATE utf8_unicode_ci NOT NULL,
  `enabled` tinyint(1) NOT NULL,
  `salt` varchar(255) COLLATE utf8_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `confirmation_token` varchar(180) COLLATE utf8_unicode_ci DEFAULT NULL,
  `password_requested_at` datetime DEFAULT NULL,
  `roles` longtext COLLATE utf8_unicode_ci NOT NULL COMMENT '(DC2Type:array)',
  `fullname` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `address` varchar(255) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Дамп данных таблицы `customer`
--

INSERT INTO `customer` (`id`, `username`, `username_canonical`, `email`, `email_canonical`, `enabled`, `salt`, `password`, `last_login`, `confirmation_token`, `password_requested_at`, `roles`, `fullname`, `address`) VALUES
(1, 'root', 'root', 'root@server.com', 'root@server.com', 1, 'fhq7i8oleu8kwc0s4k0gks4scswkko0', '$2y$13$fhq7i8oleu8kwc0s4k0gkevzxnrngZA0s9mxuwYfVtlbgnyTwvsq2', '2016-12-21 12:27:00', NULL, NULL, 'a:2:{i:0;s:10:"ROLE_ADMIN";i:1;s:9:"ROLE_USER";}', 'Rooty', 'USA california 1234  drive'),
(2, 'admin', 'admin', 'admin@server.com', 'admin@server.com', 1, 'nvrvt2gezqos0wggo4soogwgccs0c0w', '$2y$13$nvrvt2gezqos0wggo4sooe0xojcQwHhmmZ/9YFTGS1rB9j3OlEzPm', '2017-01-18 12:43:59', NULL, NULL, 'a:1:{i:0;s:16:"ROLE_SUPER_ADMIN";}', 'Admincher', 'Administan cityOfAdmins 8bit drive 123'),
(3, 'user', 'user', 'u@ser.com', 'u@ser.com', 1, '1rzmdvxduijow4sgksoo40w0so0ksk4', '$2y$13$1rzmdvxduijow4sgksoo4uvSvO7co9qKprCJKgXu02eiq3jPb1XTu', '2016-12-19 14:45:22', NULL, NULL, 'a:0:{}', 'Homo erectus', 'Userland Usercity Olduser Boulevard 123'),
(4, 'guest', 'guest', 'guest@server.co.m', 'guest@server.co.m', 1, '84ex0ftipjwgg0c4ckk8kcggow08wok', '$2y$13$84ex0ftipjwgg0c4ckk8kOrOa1KEhKMKigisWhvDayeiirBIJ6Afm', '2016-12-21 12:27:13', 'hBX76GtUIaPPv2d6R5Ao-uqnD2s08IJuTe0_z0tqIro', '2016-12-22 11:57:29', 'a:0:{}', 'Guest g uest', 'Userland2 Usercity Olduser Boulevard 123');

-- --------------------------------------------------------

--
-- Структура таблицы `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `product` int(11) DEFAULT NULL,
  `customer` int(11) DEFAULT NULL,
  `shipping` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `quantity` int(11) NOT NULL,
  `sold` tinyint(1) NOT NULL,
  `created` datetime NOT NULL,
  `edited` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Дамп данных таблицы `orders`
--

INSERT INTO `orders` (`id`, `product`, `customer`, `shipping`, `quantity`, `sold`, `created`, `edited`) VALUES
(7, 15, 2, 'Administan cityOfAdmins 8bit drive 123', 3, 1, '2017-01-12 23:16:00', '2017-01-12 23:16:00'),
(16, 13, 2, 'Administan cityOfAdmins 8bit drive 123', 1, 0, '2017-01-13 19:36:01', '2017-01-13 19:36:01'),
(17, 13, 2, 'Administan cityOfAdmins 8bit drive 123', 1, 0, '2017-01-13 19:37:16', '2017-01-13 19:37:16'),
(18, 1, 2, 'Administan cityOfAdmins 8bit drive 123', 1, 1, '2017-01-16 14:11:25', '2017-01-16 14:11:25'),
(19, 14, 2, 'Administan cityOfAdmins 8bit drive 123', 1, 0, '2017-01-19 09:57:32', '2017-01-19 09:57:32'),
(20, 14, 2, 'Administan cityOfAdmins 8bit drive 123', 1, 0, '2017-01-19 09:59:50', '2017-01-19 09:59:50'),
(21, 14, 2, 'Administan cityOfAdmins 8bit drive 123', 1, 0, '2017-01-19 10:00:00', '2017-01-19 10:00:00'),
(22, 14, 2, 'Administan cityOfAdmins 8bit drive 123', 1, 0, '2017-01-19 10:00:16', '2017-01-19 10:00:16'),
(23, 14, 2, 'Administan cityOfAdmins 8bit drive 123', 1, 0, '2017-01-19 10:00:30', '2017-01-19 10:00:30'),
(24, 3, 2, 'Administan cityOfAdmins 8bit drive 123', 1, 0, '2017-01-19 10:05:24', '2017-01-19 10:05:24');

-- --------------------------------------------------------

--
-- Структура таблицы `product`
--

CREATE TABLE `product` (
  `id` int(11) NOT NULL,
  `сategory` int(11) DEFAULT NULL,
  `title` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `slug` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `description` longtext COLLATE utf8_unicode_ci NOT NULL,
  `available` int(11) NOT NULL,
  `added` datetime NOT NULL,
  `viewed` int(11) NOT NULL,
  `lastViewed` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Дамп данных таблицы `product`
--

INSERT INTO `product` (`id`, `сategory`, `title`, `slug`, `price`, `description`, `available`, `added`, `viewed`, `lastViewed`) VALUES
(1, 2, '10 g ru gold', '10-g-ru-gold', '13.50', 'awesome 10 g gold from Russia with love', 8, '2016-12-22 09:01:00', 21, '2017-01-16 14:11:25'),
(2, 3, 'random granules', 'random-granules', '1.13', 'Perfect, clear 1$ per gram, 1,5 kg available !!!', 1500, '2016-12-22 09:34:00', 7, '2017-01-13 17:12:55'),
(3, 1, 'EU fine 1kg ingot of gold', 'eu-fine-1kg-ingot-of-gold', '1299.95', 'Perfect  1 kg ingots.', 4, '2016-12-22 10:00:00', 428, '2017-01-19 10:05:24'),
(4, 6, 'a lot of gold coins', 'a-lot-of-gold-coins', '1.32', 'A lot of gold coins 1.32$ per coins', 1236, '2016-12-22 10:25:00', 215, '2017-01-17 15:13:49'),
(5, 5, 'Gold nuggets', 'gold-nuggets', '349.00', '400g of gold nuggets fonded in the middle of nowhere.', 15, '2016-12-22 10:30:00', 101, '2017-01-18 11:58:26'),
(6, 4, '8bit ingots', '8bit-ingots', '1.30', 'e-gold for on-line games', 78545, '2016-12-22 10:35:00', 156, '2017-01-19 09:39:30'),
(7, 4, 'plastic e-gold', 'plastic-e-gold', '2.15', 'Pairs of like plastic,  ingots for on-line e-gold', 45, '2016-12-22 10:50:00', 216, '2017-01-18 14:27:10'),
(8, 4, '8bit new1', '8bit-new1', '1.22', '8bit new1 desc', 4, '2017-01-10 19:03:00', 13, '2017-01-19 09:39:22'),
(9, 4, '8bit new2', '8bit-new2', '0.66', '8bit new2 desc', 2, '2017-01-10 19:04:00', 7, '2017-01-11 21:15:49'),
(10, 5, '8bit new3', '8bit-new3', '3.56', '8bit new3 desc', 1, '2017-01-10 19:04:00', 3, '2017-01-11 18:49:11'),
(11, 4, '8bit new4', '8bit-new4', '2.46', '8bit new4 desc', 6, '2017-01-10 19:04:00', 4, '2012-01-01 00:00:00'),
(12, 4, '8bit new5', '8bit-new5', '5.12', '8bit new5 desc', 3, '2017-01-10 19:05:00', 6, '2017-01-18 14:25:47'),
(13, 4, '8bit new6', '8bit-new6', '3.66', '8bit new6 desc', 9, '2017-01-10 19:05:00', 17, '2017-01-19 09:52:44'),
(14, 4, '8bit new7 UpDated', '8bit-new7', '3.71', '8bit new7 desc', 9, '2017-01-10 19:06:00', 24, '2017-01-19 10:21:33'),
(15, 4, '8bit new8', '8bit-new8', '3.80', '8bit new8 desc', 3, '2017-01-10 19:07:00', 37, '2017-01-16 17:09:57'),
(16, 4, 'test prod', 'test-prod', '1.23', 'description for test product', 6, '2017-01-19 10:22:00', 6, '2012-01-01 00:00:00');

-- --------------------------------------------------------

--
-- Структура таблицы `productimage`
--

CREATE TABLE `productimage` (
  `id` int(11) NOT NULL,
  `product` int(11) DEFAULT NULL,
  `imagename` varchar(128) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

--
-- Дамп данных таблицы `productimage`
--

INSERT INTO `productimage` (`id`, `product`, `imagename`) VALUES
(1, NULL, 'testimage.jpg'),
(2, NULL, 'text234.jpg'),
(3, 1, 'ru.10g.png'),
(4, 1, 'ru2.10g.png.jpg'),
(5, 2, 'granules2.jpg'),
(6, 2, 'granules3.jpg'),
(7, 2, 'granules4.jpg'),
(8, 2, 'granules5.jpg'),
(9, 3, 'eu.fine1.1kg.png'),
(10, 3, 'eu.fine2.1kg.png'),
(11, 4, 'coins1.jpg'),
(12, 4, 'coins2.png'),
(13, 5, 'nugget1.png'),
(14, 5, 'nugget2.png'),
(15, 6, '8bit.800g.png'),
(16, 7, '8bit.plastic.500g.png');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `category`
--
ALTER TABLE `category`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_64C19C12B36786B` (`title`),
  ADD UNIQUE KEY `UNIQ_64C19C1989D9B62` (`slug`);

--
-- Индексы таблицы `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_81398E0992FC23A8` (`username_canonical`),
  ADD UNIQUE KEY `UNIQ_81398E09A0D96FBF` (`email_canonical`),
  ADD UNIQUE KEY `UNIQ_81398E09C05FB297` (`confirmation_token`);

--
-- Индексы таблицы `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_E52FFDEED34A04AD` (`product`),
  ADD KEY `IDX_E52FFDEE81398E09` (`customer`);

--
-- Индексы таблицы `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_D34A04AD989D9B62` (`slug`),
  ADD KEY `IDX_D34A04AD47EEA560` (`сategory`);

--
-- Индексы таблицы `productimage`
--
ALTER TABLE `productimage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_15F056E5D34A04AD` (`product`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `category`
--
ALTER TABLE `category`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;
--
-- AUTO_INCREMENT для таблицы `customer`
--
ALTER TABLE `customer`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
--
-- AUTO_INCREMENT для таблицы `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;
--
-- AUTO_INCREMENT для таблицы `product`
--
ALTER TABLE `product`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
--
-- AUTO_INCREMENT для таблицы `productimage`
--
ALTER TABLE `productimage`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;
--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `FK_E52FFDEE81398E09` FOREIGN KEY (`customer`) REFERENCES `customer` (`id`),
  ADD CONSTRAINT `FK_E52FFDEED34A04AD` FOREIGN KEY (`product`) REFERENCES `product` (`id`);

--
-- Ограничения внешнего ключа таблицы `product`
--
ALTER TABLE `product`
  ADD CONSTRAINT `FK_D34A04AD47EEA560` FOREIGN KEY (`сategory`) REFERENCES `category` (`id`);

--
-- Ограничения внешнего ключа таблицы `productimage`
--
ALTER TABLE `productimage`
  ADD CONSTRAINT `FK_15F056E5D34A04AD` FOREIGN KEY (`product`) REFERENCES `product` (`id`);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
