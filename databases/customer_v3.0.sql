-- phpMyAdmin SQL Dump
-- version 4.5.4.1deb2ubuntu2
-- http://www.phpmyadmin.net
--
-- Хост: localhost
-- Время создания: Янв 12 2017 г., 17:37
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
(1, 'root', 'root', 'root@server.com', 'root@server.com', 1, 'fhq7i8oleu8kwc0s4k0gks4scswkko0', '$2y$13$fhq7i8oleu8kwc0s4k0gkevzxnrngZA0s9mxuwYfVtlbgnyTwvsq2', '2016-12-21 12:27:27', NULL, NULL, 'a:2:{i:0;s:10:"ROLE_ADMIN";i:1;s:9:"ROLE_USER";}', 'Rooty', 'USA california 1234  drive'),
(2, 'admin', 'admin', 'admin@server.com', 'admin@server.com', 1, 'nvrvt2gezqos0wggo4soogwgccs0c0w', '$2y$13$nvrvt2gezqos0wggo4sooe0xojcQwHhmmZ/9YFTGS1rB9j3OlEzPm', '2017-01-12 17:24:33', NULL, NULL, 'a:1:{i:0;s:16:"ROLE_SUPER_ADMIN";}', 'Admincher', 'Administan cityOfAdmins 8bit drive 123'),
(3, 'user', 'user', 'u@ser.com', 'u@ser.com', 1, '1rzmdvxduijow4sgksoo40w0so0ksk4', '$2y$13$1rzmdvxduijow4sgksoo4uvSvO7co9qKprCJKgXu02eiq3jPb1XTu', '2016-12-19 14:45:22', NULL, NULL, 'a:0:{}', 'Homo erectus', 'Userland Usercity Olduser Boulevard 123'),
(4, 'guest', 'guest', 'guest@server.co.m', 'guest@server.co.m', 1, '84ex0ftipjwgg0c4ckk8kcggow08wok', '$2y$13$84ex0ftipjwgg0c4ckk8kOrOa1KEhKMKigisWhvDayeiirBIJ6Afm', '2016-12-21 12:27:13', 'hBX76GtUIaPPv2d6R5Ao-uqnD2s08IJuTe0_z0tqIro', '2016-12-22 11:57:29', 'a:0:{}', 'Guest g uest', 'Userland2 Usercity Olduser Boulevard 123');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `customer`
--
ALTER TABLE `customer`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `UNIQ_81398E0992FC23A8` (`username_canonical`),
  ADD UNIQUE KEY `UNIQ_81398E09A0D96FBF` (`email_canonical`),
  ADD UNIQUE KEY `UNIQ_81398E09C05FB297` (`confirmation_token`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `customer`
--
ALTER TABLE `customer`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
