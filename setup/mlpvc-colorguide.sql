SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

DROP DATABASE `mlpvc-colorguide`;
CREATE DATABASE `mlpvc-colorguide` DEFAULT CHARACTER SET utf8 COLLATE utf8_general_ci;
USE `mlpvc-colorguide`;

CREATE TABLE IF NOT EXISTS `colorgroups` (
  `groupid` int(11) NOT NULL,
  `ponyid` int(11) NOT NULL,
  `label` varchar(255) NOT NULL,
  `order` tinyint(4) NOT NULL DEFAULT '0'
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8;

INSERT INTO `colorgroups` (`groupid`, `ponyid`, `label`, `order`) VALUES
(1, 1, 'Mane', 0),
(2, 1, 'Coat', 1),
(3, 1, 'Eyes', 2),
(4, 1, 'Cutie Mark/Magic', 3),
(5, 2, 'Base', 0),
(6, 2, 'Shoes (Foreground)', 1),
(7, 2, 'Shoes (Background)', 2),
(8, 2, 'Saddle', 3),
(9, 3, 'Mane', 0),
(10, 3, 'Coat', 1),
(11, 3, 'Eyes', 2),
(12, 3, 'Cutie Mark', 3);

CREATE TABLE IF NOT EXISTS `colors` (
  `colorid` int(11) NOT NULL,
  `groupid` int(11) NOT NULL,
  `label` tinytext NOT NULL,
  `hex` varchar(7) NOT NULL,
  `order` tinyint(4) DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8;

INSERT INTO `colors` (`colorid`, `groupid`, `label`, `hex`, `order`) VALUES
(1, 1, 'Outline', '#132042', 0),
(2, 1, 'Fill', '#273771', 1),
(3, 1, 'Purple Streak', '#622E86', 2),
(4, 1, 'Pink Streak', '#E6458B', 3),
(5, 2, 'Outline', '#A66EBE', 0),
(6, 2, 'Fill', '#D4A4E8', 1),
(7, 2, 'Shadow Outline', '#9964AC', 2),
(8, 2, 'Shadow Fill', '#AE80C4', 3),
(9, 3, 'Gradient Top', '#210045', 0),
(10, 3, 'Gradient Bottom', '#64128D', 1),
(11, 3, 'Dark Highlight', '#8C4FAB', 2),
(12, 3, 'Light Highlight', '#CCAED7', 3),
(13, 4, 'Pink Star/Magic Glow', '#E6458B', 0),
(14, 4, 'White Stars', '#FFFFFF', 1),
(15, 5, 'Outline', '#2A3E6F', 0),
(16, 5, 'Fill', '#4365B7', 1),
(17, 5, 'Darkest Stars & Bottom', '#7591CD', 2),
(18, 5, 'Blue Stars', '#AFD7E9', 3),
(19, 5, 'White Stars', '#FFFFFF', 4),
(20, 6, 'Turquoise Outline', '#64B0CA', 0),
(21, 6, 'Grayish Outline', '#C8DBB3', 1),
(22, 6, 'Fill', '#AFD7E9', 2),
(23, 6, 'Deep Blue Star', '#4365B7', 3),
(26, 7, 'Turquoise Outline', '#6D8EB2', 0),
(27, 7, 'Fill', '#99A4C4', 1),
(28, 7, 'Deep Blue Star', '#5861A3', 2),
(29, 8, 'Outline', '#64B0CA', 0),
(30, 8, 'Fill', '#AFD7E9', 1),
(31, 8, 'Blue Stars', '#7591CD', 2),
(32, 8, 'White Stars', '#FFFFFF', 3),
(33, 9, 'Light Outline', '#A1A4D7', 0),
(34, 9, 'Light Fill', '#D2D5F1', 1),
(35, 9, 'Dark Outline', '#30309A', 2),
(36, 9, 'Dark Fill', '#3A39B3', 3),
(37, 10, 'Outline', '#447DC0', 0),
(38, 10, 'Fill', '#84C8F3', 1),
(39, 10, 'Shadow Fill', '#7BBEEA', 2),
(40, 11, 'Gradient Top', '#1E1C61', 0),
(41, 11, 'Gradient Bottom', '#516CD2', 1),
(42, 11, 'Dark Highlight', '#ABB0FF', 2),
(43, 11, 'Light Highlight', '#D8E1FF', 3),
(46, 12, 'Base Fill', '#FFCE00', 0),
(47, 12, 'Base Shine', '#F4EF9C', 1),
(48, 12, 'Glass', '#BDE7F4', 2),
(49, 12, 'Sand', '#DFD47B', 3),
(50, 12, 'Glass Shine', '#FFFFFF', 4);

CREATE TABLE IF NOT EXISTS `ponies` (
  `id` int(11) NOT NULL,
  `label` tinytext NOT NULL,
  `notes` tinytext NOT NULL,
  `added` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

INSERT INTO `ponies` (`id`, `label`, `notes`, `added`) VALUES
(1, 'Twilight Sparkle', 'Far legs use a darker stroke color', '2015-07-25 14:49:44'),
(2, 'Twilight Sparkle''s gala dress', '', '2015-07-25 14:49:44'),
(3, 'Colgate Minuette', '', '2015-07-25 14:49:44');

CREATE TABLE IF NOT EXISTS `tagged` (
  `tid` int(11) NOT NULL,
  `ponyid` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `tagged` (`tid`, `ponyid`) VALUES
(1, 1),
(6, 1),
(12, 1),
(14, 1),
(1, 2),
(6, 2),
(12, 2),
(14, 2),
(15, 2),
(20, 2),
(1, 3),
(7, 3),
(12, 3),
(19, 3),
(21, 3);

CREATE TABLE IF NOT EXISTS `tags` (
  `tid` int(11) NOT NULL,
  `name` varchar(25) NOT NULL,
  `title` tinytext NOT NULL,
  `type` enum('spec','gen','cat','app','ep') DEFAULT NULL,
  `uses` int(11) NOT NULL DEFAULT '0'
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8;

INSERT INTO `tags` (`tid`, `name`, `title`, `type`, `uses`) VALUES
(1, 'unicorn', '', 'spec', 3),
(2, 'earth pony', '', 'spec', 0),
(3, 'pegasus', '', 'spec', 0),
(4, 'alicorn', '', 'spec', 0),
(5, 'bat pony', '', 'spec', 0),
(6, 'mane six', 'Ponies who are one of the show''s six main characters', 'cat', 2),
(7, 'minor character', 'Ponies who had a speaking role and/or interacted with the mane six', 'cat', 1),
(8, 'background character', 'Ponies whose only purpose is filling crowds, with no to minimal speaking roles', 'cat', 0),
(9, 'antagonist', '', 'cat', 0),
(10, 'pet', '', 'cat', 0),
(11, 'male', '', 'gen', 0),
(12, 'female', '', 'gen', 3),
(14, 'twilight sparkle', 'All appearances related to Twilight Sparkle', NULL, 2),
(15, 'gala dresses', 'All gala dress colors', 'app', 0),
(16, 'human', 'Refers to Equestria Girls characters', 'spec', 0),
(19, 's1e1', '', 'ep', 1),
(20, 's1e26', '', 'ep', 1),
(21, 's5e12', '', 'ep', 1);


ALTER TABLE `colorgroups`
  ADD PRIMARY KEY (`groupid`),
  ADD UNIQUE KEY `groupid` (`groupid`,`ponyid`,`label`),
  ADD KEY `ponyid` (`ponyid`);

ALTER TABLE `colors`
  ADD PRIMARY KEY (`colorid`),
  ADD KEY `groupid` (`groupid`);

ALTER TABLE `ponies`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `tagged`
  ADD PRIMARY KEY (`tid`,`ponyid`),
  ADD KEY `ponyid` (`ponyid`);

ALTER TABLE `tags`
  ADD PRIMARY KEY (`tid`);


ALTER TABLE `colorgroups`
  MODIFY `groupid` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=13;
ALTER TABLE `colors`
  MODIFY `colorid` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=51;
ALTER TABLE `ponies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=4;
ALTER TABLE `tags`
  MODIFY `tid` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=22;

ALTER TABLE `colorgroups`
  ADD CONSTRAINT `colorgroups_ibfk_1` FOREIGN KEY (`ponyid`) REFERENCES `ponies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `colors`
  ADD CONSTRAINT `colors_ibfk_1` FOREIGN KEY (`groupid`) REFERENCES `colorgroups` (`groupid`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `tagged`
  ADD CONSTRAINT `tagged_ibfk_1` FOREIGN KEY (`tid`) REFERENCES `tags` (`tid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `tagged_ibfk_2` FOREIGN KEY (`ponyid`) REFERENCES `ponies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
