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
  `label` tinytext NOT NULL,
  `order` tinyint(4) NOT NULL DEFAULT '0'
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8;

INSERT INTO `colorgroups` (`groupid`, `ponyid`, `label`, `order`) VALUES
(1, 1, 'Mane', 0),
(2, 1, 'Coat', 0),
(3, 1, 'Eyes', 0),
(4, 1, 'Cutie Mark/Magic', 0),
(5, 2, 'Base', 0),
(6, 2, 'Shoes (Foreground)', 0),
(7, 2, 'Shoes (Background)', 0),
(8, 2, 'Saddle', 0),
(9, 3, 'Mane', 0),
(10, 3, 'Coat', 0),
(11, 3, 'Eyes', 0),
(12, 3, 'Cutie Mark', 0);

CREATE TABLE IF NOT EXISTS `colors` (
  `colorid` int(11) NOT NULL,
  `groupid` int(11) NOT NULL,
  `label` tinytext NOT NULL,
  `hex` varchar(7) NOT NULL,
  `order` tinyint(4) NOT NULL DEFAULT '0'
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8;

INSERT INTO `colors` (`colorid`, `groupid`, `label`, `hex`, `order`) VALUES
(1, 1, 'Outline', '#132042', 0),
(2, 1, 'Fill', '#273771', 0),
(3, 1, 'Purple Streak', '#622E86', 0),
(4, 1, 'Pink Streak', '#E6458B', 0),
(5, 2, 'Outline', '#A66EBE', 0),
(6, 2, 'Fill', '#D4A4E8', 0),
(7, 2, 'Shadow Outline', '#9964AC', 0),
(8, 2, 'Shadow Fill', '#AE80C4', 0),
(9, 3, 'Gradient Top', '#210045', 0),
(10, 3, 'Gradient Bottom', '#64128D', 0),
(11, 3, 'Dark Highlight', '#8C4FAB', 0),
(12, 3, 'Light Highlight', '#CCAED7', 0),
(13, 4, 'Pink Star/Magic Glow', '#E6458B', 0),
(14, 4, 'White Stars', '#FFFFFF', 0),
(15, 5, 'Outline', '#2A3E6F', 0),
(16, 5, 'Fill', '#4365B7', 0),
(17, 5, 'Darkest Stars & Bottom', '#7591CD', 0),
(18, 5, 'Blue Stars', '#AFD7E9', 0),
(19, 5, 'White Stars', '#FFFFFF', 0),
(20, 6, 'Turquoise Outline', '#64B0CA', 0),
(21, 6, 'Grayish Outline', '#C8DBB3', 0),
(22, 6, 'Fill', '#AFD7E9', 0),
(23, 6, 'Deep Blue Star', '#4365B7', 0),
(26, 7, 'Turquoise Outline', '#6D8EB2', 0),
(27, 7, 'Fill', '#99A4C4', 0),
(28, 7, 'Deep Blue Star', '#5861A3', 0),
(29, 8, 'Outline', '#64B0CA', 0),
(30, 8, 'Fill', '#AFD7E9', 0),
(31, 8, 'Blue Stars', '#7591CD', 0),
(32, 8, 'White Stars', '#FFFFFF', 0),
(33, 9, 'Light Outline', '#A1A4D7', 0),
(34, 9, 'Light Fill', '#D2D5F1', 0),
(35, 9, 'Dark Outline', '#30309A', 0),
(36, 9, 'Dark Fill', '#3A39B3', 0),
(37, 10, 'Outline', '#447DC0', 0),
(38, 10, 'Fill', '#84C8F3', 0),
(39, 10, 'Shadow Fill', '#7BBEEA', 0),
(40, 11, 'Gradient Top', '#1E1C61', 0),
(41, 11, 'Gradient Bottom', '#516CD2', 0),
(42, 11, 'Dark Highlight', '#ABB0FF', 0),
(43, 11, 'Light Highlight', '#D8E1FF', 0),
(46, 12, 'Base Fill', '#FFCE00', 0),
(47, 12, 'Base Shine', '#F4EF9C', 0),
(48, 12, 'Glass', '#BDE7F4', 0),
(49, 12, 'Sand', '#DFD47B', 0),
(50, 12, 'Glass Shine', '#FFFFFF', 0);

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
(1, 3),
(7, 3),
(12, 3);

CREATE TABLE IF NOT EXISTS `tags` (
  `tid` int(11) NOT NULL,
  `name` varchar(25) NOT NULL,
  `title` tinytext NOT NULL,
  `type` enum('spec','gen','cat','app','ep') DEFAULT NULL
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8;

INSERT INTO `tags` (`tid`, `name`, `title`, `type`) VALUES
(1, 'unicorn', '', 'spec'),
(2, 'earth pony', '', 'spec'),
(3, 'pegasus', '', 'spec'),
(4, 'alicorn', '', 'spec'),
(5, 'bat pony', '', 'spec'),
(6, 'mane six', 'Ponies who are one of the show''s six main characters', 'cat'),
(7, 'minor character', 'Ponies who had a speaking role and/or interacted with the mane six', 'cat'),
(8, 'background character', 'Ponies whose only purpose is filling crowds, with no to minimal speaking roles', 'cat'),
(9, 'antagonist', '', 'cat'),
(10, 'pet', '', 'cat'),
(11, 'male', '', 'gen'),
(12, 'female', '', 'gen'),
(13, 'ambiguous', '', 'gen'),
(14, 'twilight sparkle', 'All appearances related to Twilight Sparkle', NULL),
(15, 'gala dresses', 'All gala dress colors', 'app'),
(16, 'human', 'Refers to Equestria Girls characters', 'spec'),
(17, 'pets', '', 'cat'),
(18, 'amending fences', '', 'ep');


ALTER TABLE `colorgroups`
  ADD PRIMARY KEY (`groupid`),
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
  MODIFY `tid` int(11) NOT NULL AUTO_INCREMENT,AUTO_INCREMENT=19;

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
