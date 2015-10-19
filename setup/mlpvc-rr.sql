SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

CREATE DATABASE IF NOT EXISTS `mlpvc-rr` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `mlpvc-rr`;

CREATE TABLE `deviation_cache` (
  `provider` enum('fav.me','sta.sh') NOT NULL DEFAULT 'fav.me',
  `id` varchar(20) NOT NULL,
  `title` tinytext NOT NULL,
  `preview` tinytext NOT NULL,
  `fullsize` tinytext NOT NULL,
  `updated_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `episodes` (
  `season` tinyint(2) UNSIGNED NOT NULL,
  `episode` tinyint(2) UNSIGNED NOT NULL,
  `twoparter` tinyint(1) NOT NULL DEFAULT '0',
  `title` tinytext NOT NULL,
  `posted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `posted_by` varchar(36) DEFAULT NULL,
  `airs` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `episodes__videos` (
  `season` tinyint(2) UNSIGNED NOT NULL,
  `episode` tinyint(2) UNSIGNED NOT NULL,
  `provider` enum('yt','dm') NOT NULL,
  `id` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `episodes__votes` (
  `season` tinyint(2) UNSIGNED NOT NULL,
  `episode` tinyint(2) UNSIGNED NOT NULL,
  `user` varchar(36) NOT NULL,
  `vote` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `log` (
  `entryid` int(11) NOT NULL,
  `initiator` varchar(36) DEFAULT NULL,
  `reftype` tinytext NOT NULL,
  `refid` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` tinytext
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `log__banish` (
  `entryid` int(11) NOT NULL,
  `target` varchar(36) NOT NULL,
  `reason` tinytext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `log__color_modify` (
  `entryid` int(11) NOT NULL,
  `ponyid` int(11) DEFAULT NULL,
  `reason` tinytext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `log__episodes` (
  `entryid` int(11) NOT NULL,
  `action` enum('add','del') NOT NULL,
  `season` tinyint(2) UNSIGNED NOT NULL,
  `episode` tinyint(2) UNSIGNED NOT NULL,
  `twoparter` tinyint(1) NOT NULL,
  `title` tinytext NOT NULL,
  `airs` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `log__episode_modify` (
  `entryid` int(11) NOT NULL,
  `target` tinytext NOT NULL,
  `oldseason` tinyint(2) UNSIGNED DEFAULT NULL,
  `newseason` tinyint(2) UNSIGNED DEFAULT NULL,
  `oldepisode` tinyint(2) UNSIGNED DEFAULT NULL,
  `newepisode` tinyint(2) UNSIGNED DEFAULT NULL,
  `oldtwoparter` tinyint(1) DEFAULT NULL,
  `newtwoparter` tinyint(1) DEFAULT NULL,
  `oldtitle` tinytext,
  `newtitle` tinytext,
  `oldairs` timestamp NULL DEFAULT NULL,
  `newairs` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `log__img_update` (
  `entryid` int(11) NOT NULL,
  `id` int(11) DEFAULT NULL,
  `thing` char(11) DEFAULT NULL,
  `oldpreview` tinytext,
  `oldfullsize` tinytext,
  `newpreview` tinytext,
  `newfullsize` tinytext
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `log__post_lock` (
  `entryid` int(11) NOT NULL,
  `type` enum('request','reservation') NOT NULL,
  `id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `log__req_delete` (
  `entryid` int(11) NOT NULL,
  `id` int(11) DEFAULT NULL,
  `season` tinyint(3) UNSIGNED DEFAULT NULL,
  `episode` tinyint(3) UNSIGNED DEFAULT NULL,
  `label` varchar(255) DEFAULT NULL,
  `type` char(4) DEFAULT NULL,
  `requested_by` varchar(36) DEFAULT NULL,
  `posted` timestamp NULL DEFAULT NULL,
  `reserved_by` varchar(36) DEFAULT NULL,
  `deviation_id` varchar(7) DEFAULT NULL,
  `lock` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `log__rolechange` (
  `entryid` int(11) NOT NULL,
  `target` varchar(36) NOT NULL,
  `oldrole` varchar(10) NOT NULL,
  `newrole` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `log__un-banish` (
  `entryid` int(11) NOT NULL,
  `target` varchar(36) NOT NULL,
  `reason` tinytext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `log__userfetch` (
  `entryid` int(11) NOT NULL,
  `userid` varchar(36) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `permissions` (
  `action` varchar(30) NOT NULL,
  `minrole` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `requests` (
  `id` int(11) NOT NULL,
  `type` enum('chr','bg','obj') NOT NULL DEFAULT 'chr',
  `season` tinyint(2) UNSIGNED NOT NULL,
  `episode` tinyint(2) UNSIGNED NOT NULL,
  `preview` tinytext NOT NULL,
  `fullsize` tinytext NOT NULL,
  `label` tinytext NOT NULL,
  `requested_by` varchar(36) DEFAULT NULL,
  `posted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reserved_by` varchar(36) DEFAULT NULL,
  `deviation_id` varchar(7) DEFAULT NULL,
  `lock` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `season` tinyint(2) UNSIGNED NOT NULL,
  `episode` tinyint(2) UNSIGNED NOT NULL,
  `preview` tinytext NOT NULL,
  `fullsize` tinytext NOT NULL,
  `label` tinytext NOT NULL,
  `posted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reserved_by` varchar(36) NOT NULL,
  `deviation_id` varchar(7) DEFAULT NULL,
  `lock` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `roles` (
  `value` tinyint(3) UNSIGNED NOT NULL,
  `name` varchar(10) NOT NULL,
  `label` tinytext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user` varchar(36) NOT NULL,
  `platform` tinytext NOT NULL,
  `browser_name` tinytext,
  `browser_ver` tinytext,
  `user_agent` varchar(300) DEFAULT NULL,
  `token` varchar(40) NOT NULL,
  `access` varchar(50) NOT NULL,
  `refresh` varchar(40) NOT NULL,
  `expires` timestamp NULL DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lastvisit` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `usefullinks` (
  `id` int(11) NOT NULL,
  `url` tinytext NOT NULL,
  `label` varchar(40) NOT NULL,
  `title` tinytext NOT NULL,
  `minrole` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `users` (
  `id` varchar(36) NOT NULL,
  `name` tinytext NOT NULL,
  `role` varchar(10) NOT NULL DEFAULT 'user',
  `avatar_url` tinytext NOT NULL,
  `signup_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


ALTER TABLE `deviation_cache`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `episodes`
  ADD PRIMARY KEY (`season`,`episode`),
  ADD KEY `posted_by` (`posted_by`);

ALTER TABLE `episodes__videos`
  ADD PRIMARY KEY (`season`,`episode`,`provider`) USING BTREE;

ALTER TABLE `episodes__votes`
  ADD PRIMARY KEY (`season`,`episode`,`user`) USING BTREE,
  ADD KEY `user` (`user`);

ALTER TABLE `log`
  ADD PRIMARY KEY (`entryid`),
  ADD KEY `initiator` (`initiator`);

ALTER TABLE `log__banish`
  ADD PRIMARY KEY (`entryid`),
  ADD KEY `target` (`target`);

ALTER TABLE `log__color_modify`
  ADD PRIMARY KEY (`entryid`),
  ADD KEY `ponyid` (`ponyid`);

ALTER TABLE `log__episodes`
  ADD PRIMARY KEY (`entryid`);

ALTER TABLE `log__episode_modify`
  ADD PRIMARY KEY (`entryid`);

ALTER TABLE `log__img_update`
  ADD PRIMARY KEY (`entryid`);

ALTER TABLE `log__post_lock`
  ADD PRIMARY KEY (`entryid`);

ALTER TABLE `log__req_delete`
  ADD PRIMARY KEY (`entryid`);

ALTER TABLE `log__rolechange`
  ADD PRIMARY KEY (`entryid`),
  ADD KEY `prevrole` (`oldrole`),
  ADD KEY `newrole` (`newrole`),
  ADD KEY `target` (`target`);

ALTER TABLE `log__un-banish`
  ADD PRIMARY KEY (`entryid`),
  ADD KEY `target` (`target`);

ALTER TABLE `log__userfetch`
  ADD PRIMARY KEY (`entryid`);

ALTER TABLE `permissions`
  ADD PRIMARY KEY (`action`),
  ADD KEY `minrole` (`minrole`);

ALTER TABLE `requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `season` (`season`,`episode`),
  ADD KEY `reserved_by` (`reserved_by`),
  ADD KEY `requested_by` (`requested_by`);

ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `season` (`season`),
  ADD KEY `episode` (`episode`),
  ADD KEY `reservations_ibfk_1` (`season`,`episode`),
  ADD KEY `reserved_by` (`reserved_by`);

ALTER TABLE `roles`
  ADD PRIMARY KEY (`value`),
  ADD KEY `name` (`name`);

ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user` (`user`);

ALTER TABLE `usefullinks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `minrole` (`minrole`);

ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `role` (`role`);


ALTER TABLE `log`
  MODIFY `entryid` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `log__banish`
  MODIFY `entryid` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `log__color_modify`
  MODIFY `entryid` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `log__episodes`
  MODIFY `entryid` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `log__episode_modify`
  MODIFY `entryid` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `log__img_update`
  MODIFY `entryid` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `log__post_lock`
  MODIFY `entryid` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `log__req_delete`
  MODIFY `entryid` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `log__rolechange`
  MODIFY `entryid` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `log__un-banish`
  MODIFY `entryid` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `log__userfetch`
  MODIFY `entryid` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;
ALTER TABLE `usefullinks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `episodes`
  ADD CONSTRAINT `episodes_ibfk_1` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `episodes__videos`
  ADD CONSTRAINT `episodes__videos_ibfk_1` FOREIGN KEY (`season`,`episode`) REFERENCES `episodes` (`season`, `episode`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `episodes__votes`
  ADD CONSTRAINT `episodes__votes_ibfk_1` FOREIGN KEY (`season`,`episode`) REFERENCES `episodes` (`season`, `episode`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `episodes__votes_ibfk_2` FOREIGN KEY (`user`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `log`
  ADD CONSTRAINT `log_ibfk_1` FOREIGN KEY (`initiator`) REFERENCES `users` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE;

ALTER TABLE `log__banish`
  ADD CONSTRAINT `log__banish_ibfk_1` FOREIGN KEY (`target`) REFERENCES `users` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE;

ALTER TABLE `log__rolechange`
  ADD CONSTRAINT `log__rolechange_ibfk_1` FOREIGN KEY (`oldrole`) REFERENCES `roles` (`name`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `log__rolechange_ibfk_2` FOREIGN KEY (`newrole`) REFERENCES `roles` (`name`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `log__un-banish`
  ADD CONSTRAINT `log__un-banish_ibfk_1` FOREIGN KEY (`target`) REFERENCES `users` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE;

ALTER TABLE `permissions`
  ADD CONSTRAINT `permissions_ibfk_1` FOREIGN KEY (`minrole`) REFERENCES `roles` (`name`);

ALTER TABLE `requests`
  ADD CONSTRAINT `requests_ibfk_2` FOREIGN KEY (`reserved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `requests_ibfk_3` FOREIGN KEY (`season`,`episode`) REFERENCES `episodes` (`season`, `episode`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `requests_ibfk_4` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`season`,`episode`) REFERENCES `episodes` (`season`, `episode`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`reserved_by`) REFERENCES `users` (`id`) ON DELETE NO ACTION ON UPDATE CASCADE;

ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `usefullinks`
  ADD CONSTRAINT `usefullinks_ibfk_1` FOREIGN KEY (`minrole`) REFERENCES `roles` (`name`);

ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role`) REFERENCES `roles` (`name`);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
