SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;


CREATE TABLE IF NOT EXISTS `roles` (
  `value` tinyint(3) unsigned NOT NULL,
  `name` varchar(10) NOT NULL,
  `label` tinytext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `roles` (`value`, `name`, `label`) VALUES
(0, 'ban', 'Banned User'),
(1, 'user', 'deviantArt User'),
(2, 'member', 'Group Member'),
(3, 'inspector', 'Vector Inspector'),
(4, 'manager', 'Group Manager'),
(5, 'founder', 'Group Founder'),
(255, 'developer', 'Site Developer');

CREATE TABLE IF NOT EXISTS `users` (
  `id` varchar(36) NOT NULL,
  `username` tinytext NOT NULL,
  `role` varchar(10) NOT NULL DEFAULT 'user',
  `avatar_url` tinytext NOT NULL,
  `access_token` tinytext NOT NULL,
  `refresh_token` tinytext NOT NULL,
  `token_expires` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `roles`
  ADD PRIMARY KEY (`value`), ADD KEY `name` (`name`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`), ADD KEY `role` (`role`);


ALTER TABLE `users`
ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role`) REFERENCES `roles` (`name`) ON DELETE NO ACTION ON UPDATE NO ACTION;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
