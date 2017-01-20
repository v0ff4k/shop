-- phpMyAdmin SQL Dump
-- version 4.5.4.1deb2ubuntu2
-- http://www.phpmyadmin.net
--
-- Хост: localhost
-- Время создания: Янв 12 2017 г., 17:36
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
-- Структура таблицы `product`
--

CREATE TABLE `product` (
  `id` int(11) NOT NULL,
  `сategory` int(11) DEFAULT NULL,
  `title` varchar(128) COLLATE utf8_unicode_ci NOT NULL,
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

INSERT INTO `product` (`id`, `сategory`, `title`, `price`, `description`, `available`, `added`, `viewed`, `lastViewed`) VALUES
(1, 2, '10 g ru gold', '13.50', 'awesome 10 g gold from Russia with love', 9, '2016-12-22 09:01:00', 16, '2017-01-05 12:56:27'),
(2, 3, 'random granules', '1.13', 'Perfect, clear 1$ per gram, 1,5 kg available !!!', 1500, '2016-12-22 09:34:00', 4, '2017-01-05 12:56:20'),
(3, 1, 'EU fine 1kg ingot of gold', '1299.95', 'Perfect  1 kg ingots.', 4, '2016-12-22 10:00:00', 427, '2017-01-12 12:11:58'),
(4, 6, 'a lot of gold coins', '1.32', 'A lot of gold coins 1.32$ per coins', 1236, '2016-12-22 10:25:00', 202, '2017-01-12 10:22:08'),
(5, 5, 'Gold nuggets', '349.00', '400g of gold nuggets fonded in the middle of nowhere.', 15, '2016-12-22 10:30:00', 88, '2017-01-12 10:57:28'),
(6, 4, '8bit ingots', '1.30', 'e-gold for on-line games', 78545, '2016-12-22 10:35:00', 150, '2017-01-12 13:17:33'),
(7, 4, 'plastic e-gold', '2.15', 'Pairs of like plastic,  ingots for on-line e-gold', 0, '2016-12-22 10:50:00', 200, '2016-12-22 10:53:00'),
(8, 4, '8bit new1', '1.22', '8bit new1 desc', 4, '2017-01-10 19:03:00', 4, '2017-01-11 21:15:36'),
(9, 4, '8bit new2', '0.66', '8bit new2 desc', 2, '2017-01-10 19:04:00', 7, '2017-01-11 21:15:49'),
(10, 5, '8bit new3', '3.56', '8bit new3 desc', 1, '2017-01-10 19:04:00', 3, '2017-01-11 18:49:11'),
(11, 4, '8bit new4', '2.46', '8bit new4 desc', 6, '2017-01-10 19:04:00', 4, '2012-01-01 00:00:00'),
(12, 4, '8bit new5', '5.12', '8bit new5 desc', 3, '2017-01-10 19:05:00', 5, '2012-01-01 00:00:00'),
(13, 4, '8bit new6', '3.66', '8bit new6 desc', 9, '2017-01-10 19:05:00', 4, '2012-01-01 00:00:00'),
(14, 4, '8bit new7', '3.71', '8bit new7 desc', 9, '2017-01-10 19:06:00', 9, '2017-01-12 16:24:33'),
(15, 4, '8bit new8', '3.80', '8bit new8 desc', 3, '2017-01-10 19:07:00', 45, '2017-01-12 11:47:01');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `product`
--
ALTER TABLE `product`
  ADD PRIMARY KEY (`id`),
  ADD KEY `IDX_D34A04AD47EEA560` (`сategory`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `product`
--
ALTER TABLE `product`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;
--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `product`
--
ALTER TABLE `product`
  ADD CONSTRAINT `FK_D34A04AD47EEA560` FOREIGN KEY (`сategory`) REFERENCES `category` (`id`);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
