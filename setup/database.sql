SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

CREATE TABLE IF NOT EXISTS `cg__colorgroups` (
  `groupid` int(11) NOT NULL,
  `ponyid` int(11) NOT NULL,
  `label` tinytext NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8;

INSERT INTO `cg__colorgroups` (`groupid`, `ponyid`, `label`) VALUES
(1, 1, 'Mane'),
(2, 1, 'Coat'),
(3, 1, 'Eyes'),
(4, 1, 'Cutie Mark/Magic'),
(5, 2, 'Base'),
(6, 2, 'Shoes (Foreground)'),
(7, 2, 'Shoes (Background)'),
(8, 2, 'Saddle'),
(9, 3, 'Mane'),
(10, 3, 'Coat'),
(11, 3, 'Eyes'),
(12, 3, 'Cutie Mark');

CREATE TABLE IF NOT EXISTS `cg__colors` (
  `colorid` int(11) NOT NULL,
  `groupid` int(11) NOT NULL,
  `label` tinytext NOT NULL,
  `hex` varchar(7) NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8;

INSERT INTO `cg__colors` (`colorid`, `groupid`, `label`, `hex`) VALUES
(1, 1, 'Outline', '#132042'),
(2, 1, 'Fill', '#273771'),
(3, 1, 'Purple Streak', '#622E86'),
(4, 1, 'Pink Streak', '#E6458B'),
(5, 2, 'Outline', '#A66EBE'),
(6, 2, 'Fill', '#D4A4E8'),
(7, 2, 'Shadow Outline', '#9964AC'),
(8, 2, 'Shadow Fill', '#AE80C4'),
(9, 3, 'Gradient Top', '#210045'),
(10, 3, 'Gradient Bottom', '#64128D'),
(11, 3, 'Dark Highlight', '#8C4FAB'),
(12, 3, 'Light Highlight', '#CCAED7'),
(13, 4, 'Pink Star/Magic Glow', '#E6458B'),
(14, 4, 'White Stars', '#FFFFFF'),
(15, 5, 'Outline', '#2A3E6F'),
(16, 5, 'Fill', '#4365B7'),
(17, 5, 'Darkest Stars & Bottom', '#7591CD'),
(18, 5, 'Blue Stars', '#AFD7E9'),
(19, 5, 'White Stars', '#FFFFFF'),
(20, 6, 'Turquoise Outline', '#64B0CA'),
(21, 6, 'Grayish Outline', '#C8DBB3'),
(22, 6, 'Fill', '#AFD7E9'),
(23, 6, 'Deep Blue Star', '#4365B7'),
(24, 3, 'Pupil', '#000000'),
(25, 3, 'Shines', '#FFFFFF'),
(26, 7, 'Turquoise Outline', '#6D8EB2'),
(27, 7, 'Fill', '#99A4C4'),
(28, 7, 'Deep Blue Star', '#5861A3'),
(29, 8, 'Outline', '#64B0CA'),
(30, 8, 'Fill', '#AFD7E9'),
(31, 8, 'Blue Stars', '#7591CD'),
(32, 8, 'White Stars', '#FFFFFF'),
(33, 9, 'Light Outline', '#A1A4D7'),
(34, 9, 'Light Fill', '#D2D5F1'),
(35, 9, 'Dark Outline', '#30309A'),
(36, 9, 'Dark Fill', '#3A39B3'),
(37, 10, 'Outline', '#447DC0'),
(38, 10, 'Fill', '#84C8F3'),
(39, 10, 'Shadow Fill', '#7BBEEA'),
(40, 11, 'Gradient Top', '#1E1C61'),
(41, 11, 'Gradient Bottom', '#516CD2'),
(42, 11, 'Dark Highlight', '#ABB0FF'),
(43, 11, 'Light Highlight', '#D8E1FF'),
(44, 11, 'Pupil', '#000000'),
(45, 11, 'Shines', '#FFFFFF'),
(46, 12, 'Base Fill', '#FFCE00'),
(47, 12, 'Base Shine', '#F4EF9C'),
(48, 12, 'Glass', '#BDE7F4'),
(49, 12, 'Sand', '#DFD47B'),
(50, 12, 'Glass Shine', '#FFFFFF');

CREATE TABLE IF NOT EXISTS `cg__ponies` (
  `id` int(11) NOT NULL,
  `label` tinytext NOT NULL,
  `notes` tinytext NOT NULL,
  `sprite` varchar(10) NOT NULL
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

INSERT INTO `cg__ponies` (`id`, `label`, `notes`, `sprite`) VALUES
(1, 'Twilight Sparkle', 'Far legs use a darker stroke color', 'tsp'),
(2, 'Twilight Sparkle''s gala dress', '', 'tsg'),
(3, 'Colgate Minuette', '', 'cgm');

CREATE TABLE IF NOT EXISTS `cg__tagged` (
  `tid` int(11) NOT NULL,
  `ponyid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `cg__tagged` (`tid`, `ponyid`) VALUES
(0, 1),
(1, 1),
(6, 1),
(12, 1),
(14, 1),
(1, 2),
(6, 2),
(12, 2),
(14, 2),
(15, 2),
(0, 3),
(1, 3),
(7, 3),
(12, 3);

CREATE TABLE IF NOT EXISTS `cg__tags` (
  `tid` int(11) NOT NULL,
  `name` varchar(25) NOT NULL,
  `title` tinytext NOT NULL,
  `type` enum('spec','gen','cat','app','ep') DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8;

INSERT INTO `cg__tags` (`tid`, `name`, `title`, `type`) VALUES
(0, 'base', 'This entry has the basic colors for the specific character', NULL),
(1, 'unicorn', '', 'spec'),
(2, 'earth pony', '', 'spec'),
(3, 'pegasus', '', 'spec'),
(4, 'alicorn', '', 'spec'),
(5, 'bat pony', '', 'spec'),
(6, 'mane six', '', 'cat'),
(7, 'minor character', '', 'cat'),
(8, 'background character', '', 'cat'),
(9, 'antagonist', '', 'cat'),
(10, 'pet', '', 'cat'),
(11, 'male', '', 'gen'),
(12, 'female', '', 'gen'),
(13, 'ambiguous', '', 'gen'),
(14, 'twilight sparkle', 'All appearances related to Twilight Sparkle', NULL),
(15, 'gala dresses', 'All gala dress colors', 'app');

CREATE TABLE IF NOT EXISTS `deviation_cache` (
  `provider` set('fav.me','sta.sh') NOT NULL DEFAULT 'fav.me',
  `id` varchar(20) NOT NULL,
  `title` tinytext NOT NULL,
  `preview` tinytext NOT NULL,
  `fullsize` tinytext NOT NULL,
  `updated_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `episodes` (
  `season` tinyint(2) unsigned NOT NULL,
  `episode` tinyint(2) unsigned NOT NULL,
  `twoparter` tinyint(1) NOT NULL DEFAULT '0',
  `title` tinytext NOT NULL,
  `posted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `posted_by` varchar(36) DEFAULT NULL,
  `airs` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `episodes__videos` (
  `season` tinyint(2) unsigned NOT NULL,
  `episode` tinyint(2) unsigned NOT NULL,
  `provider` enum('yt','dm') NOT NULL,
  `id` varchar(15) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `episodes__votes` (
  `season` tinyint(2) unsigned NOT NULL,
  `episode` tinyint(2) unsigned NOT NULL,
  `user` varchar(36) NOT NULL,
  `vote` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `log` (
  `entryid` int(11) NOT NULL,
  `initiator` varchar(36) DEFAULT NULL,
  `reftype` tinytext NOT NULL,
  `refid` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ip` tinytext
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `log__banish` (
  `entryid` int(11) NOT NULL,
  `target` varchar(36) NOT NULL,
  `reason` tinytext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `log__episodes` (
  `entryid` int(11) NOT NULL,
  `action` enum('add','del') NOT NULL,
  `season` tinyint(2) unsigned NOT NULL,
  `episode` tinyint(2) unsigned NOT NULL,
  `twoparter` tinyint(1) NOT NULL,
  `title` tinytext NOT NULL,
  `airs` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `log__episode_modify` (
  `entryid` int(11) NOT NULL,
  `target` tinytext NOT NULL,
  `oldseason` tinyint(2) unsigned DEFAULT NULL,
  `newseason` tinyint(2) unsigned DEFAULT NULL,
  `oldepisode` tinyint(2) unsigned DEFAULT NULL,
  `newepisode` tinyint(2) unsigned DEFAULT NULL,
  `oldtwoparter` tinyint(1) DEFAULT NULL,
  `newtwoparter` tinyint(1) DEFAULT NULL,
  `oldtitle` tinytext,
  `newtitle` tinytext,
  `oldairs` timestamp NULL DEFAULT NULL,
  `newairs` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `log__rolechange` (
  `entryid` int(11) NOT NULL,
  `target` varchar(36) NOT NULL,
  `oldrole` varchar(10) NOT NULL,
  `newrole` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `log__un-banish` (
  `entryid` int(11) NOT NULL,
  `target` varchar(36) NOT NULL,
  `reason` tinytext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `log__userfetch` (
  `entryid` int(11) NOT NULL,
  `userid` varchar(36) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `permissions` (
  `action` varchar(30) NOT NULL,
  `minrole` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `permissions` (`action`, `minrole`) VALUES
('users.listall', 'developer'),
('episodes.manage', 'inspector'),
('logs.view', 'inspector'),
('reservations.create', 'member');

CREATE TABLE IF NOT EXISTS `requests` (
  `id` int(11) NOT NULL,
  `type` enum('chr','bg','obj') NOT NULL DEFAULT 'chr',
  `season` tinyint(2) unsigned NOT NULL,
  `episode` tinyint(2) unsigned NOT NULL,
  `preview` tinytext NOT NULL,
  `fullsize` tinytext NOT NULL,
  `label` tinytext NOT NULL,
  `requested_by` varchar(36) DEFAULT NULL,
  `posted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reserved_by` varchar(36) DEFAULT NULL,
  `deviation_id` varchar(7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `reservations` (
  `id` int(11) NOT NULL,
  `season` tinyint(2) unsigned NOT NULL,
  `episode` tinyint(2) unsigned NOT NULL,
  `preview` tinytext NOT NULL,
  `fullsize` tinytext NOT NULL,
  `label` tinytext NOT NULL,
  `posted` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reserved_by` varchar(36) NOT NULL,
  `deviation_id` varchar(7) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `roles` (
  `value` tinyint(3) unsigned NOT NULL,
  `name` varchar(10) NOT NULL,
  `label` tinytext NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `roles` (`value`, `name`, `label`) VALUES
(0, 'ban', 'Banished User'),
(1, 'user', 'deviantArt User'),
(2, 'member', 'Club Member'),
(3, 'inspector', 'Vector Inspector'),
(4, 'manager', 'Group Manager'),
(255, 'developer', 'Site Developer');

CREATE TABLE IF NOT EXISTS `sessions` (
  `id` bigint(20) unsigned NOT NULL,
  `user` varchar(36) NOT NULL,
  `browser_name` tinytext,
  `browser_ver` tinytext,
  `access` varchar(50) NOT NULL,
  `refresh` varchar(40) NOT NULL,
  `expires` timestamp NULL DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `lastvisit` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `usefullinks` (
  `id` int(11) NOT NULL,
  `url` tinytext NOT NULL,
  `label` varchar(40) NOT NULL,
  `title` tinytext NOT NULL,
  `minrole` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `users` (
  `id` varchar(36) NOT NULL,
  `name` tinytext NOT NULL,
  `role` varchar(10) NOT NULL DEFAULT 'user',
  `avatar_url` tinytext NOT NULL,
  `signup_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


ALTER TABLE `cg__colorgroups`
  ADD PRIMARY KEY (`groupid`),
  ADD KEY `ponyid` (`ponyid`);

ALTER TABLE `cg__colors`
  ADD PRIMARY KEY (`colorid`),
  ADD KEY `groupid` (`groupid`);

ALTER TABLE `cg__ponies`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `cg__tagged`
  ADD PRIMARY KEY (`tid`,`ponyid`),
  ADD KEY `ponyid` (`ponyid`);

ALTER TABLE `cg__tags`
  ADD PRIMARY KEY (`tid`);

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

ALTER TABLE `log__episodes`
  ADD PRIMARY KEY (`entryid`);

ALTER TABLE `log__episode_modify`
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


ALTER TABLE `cg__colorgroups`
  MODIFY `groupid` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=13;
ALTER TABLE `cg__colors`
  MODIFY `colorid` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=51;
ALTER TABLE `cg__ponies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=4;
ALTER TABLE `cg__tags`
  MODIFY `tid` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=16;
ALTER TABLE `log`
  MODIFY `entryid` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `log__banish`
  MODIFY `entryid` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `log__episodes`
  MODIFY `entryid` int(11) NOT NULL AUTO_INCREMENT;
ALTER TABLE `log__episode_modify`
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
  MODIFY `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT;
ALTER TABLE `usefullinks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `cg__colorgroups`
  ADD CONSTRAINT `cg__colorgroups_ibfk_1` FOREIGN KEY (`ponyid`) REFERENCES `cg__ponies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `cg__colors`
  ADD CONSTRAINT `cg__colors_ibfk_1` FOREIGN KEY (`groupid`) REFERENCES `cg__colorgroups` (`groupid`);

ALTER TABLE `cg__tagged`
  ADD CONSTRAINT `cg__tagged_ibfk_1` FOREIGN KEY (`tid`) REFERENCES `cg__tags` (`tid`),
  ADD CONSTRAINT `cg__tagged_ibfk_2` FOREIGN KEY (`ponyid`) REFERENCES `cg__ponies` (`id`);

ALTER TABLE `episodes`
  ADD CONSTRAINT `episodes_ibfk_1` FOREIGN KEY (`posted_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `episodes__videos`
  ADD CONSTRAINT `episodes__videos_ibfk_1` FOREIGN KEY (`season`, `episode`) REFERENCES `episodes` (`season`, `episode`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `episodes__votes`
  ADD CONSTRAINT `episodes__votes_ibfk_1` FOREIGN KEY (`season`, `episode`) REFERENCES `episodes` (`season`, `episode`) ON DELETE CASCADE ON UPDATE CASCADE,
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
  ADD CONSTRAINT `requests_ibfk_3` FOREIGN KEY (`season`, `episode`) REFERENCES `episodes` (`season`, `episode`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `requests_ibfk_4` FOREIGN KEY (`requested_by`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`season`, `episode`) REFERENCES `episodes` (`season`, `episode`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `reservations_ibfk_2` FOREIGN KEY (`reserved_by`) REFERENCES `users` (`id`);

ALTER TABLE `sessions`
  ADD CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `usefullinks`
  ADD CONSTRAINT `usefullinks_ibfk_1` FOREIGN KEY (`minrole`) REFERENCES `roles` (`name`);

ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role`) REFERENCES `roles` (`name`);

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
