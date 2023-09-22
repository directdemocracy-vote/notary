SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `area` (
  `id` int(11) NOT NULL,
  `name` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `polygons` multipolygon NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ballot` (
  `id` int(11) NOT NULL,
  `stationKey` blob NOT NULL,
  `stationSignature` blob NOT NULL,
  `revoke` tinyint(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ballots` (
  `referendum` int(11) NOT NULL,
  `station` int(11) NOT NULL,
  `key` blob NOT NULL,
  `revoke` tinyint(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `citizen` (
  `id` int(11) NOT NULL,
  `givenNames` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `familyName` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `picture` blob NOT NULL,
  `home` point NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `corpus` (
  `referendum` int(11) NOT NULL,
  `citizen` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `endorsement` (
  `id` int(11) NOT NULL,
  `revoke` tinyint(1) NOT NULL,
  `message` varchar(2048) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comment` varchar(2048) COLLATE utf8mb4_unicode_ci NOT NULL,
  `endorsedSignature` blob NOT NULL,
  `latest` tinyint(1) NOT NULL,
  `accepted` tinyint(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `publication` (
  `id` int(11) NOT NULL,
  `version` smallint(6) NOT NULL,
  `type` enum('citizen', 'endorsement', 'area', 'proposal', 'participation', 'registration', 'ballot', 'vote') NOT NULL,
  `key` blob NOT NULL,
  `signature` blob NOT NULL,
  `published` bigint(15) NOT NULL,
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `proposal` (
  `id` int(11) NOT NULL,
  `judge` varchar(2048) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `area` blob NOT NULL,
  `title` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `question` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `answers` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret` tinyint(1) NOT NULL
  `deadline` bigint(15) NOT NULL,
  `website` varchar(2048) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `participants` int(11) NOT NULL,
  `corpus` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `participation` (
  `id` int(11) NOT NULL,
  `referendum` blob NOT NULL,
  `blindKey` blob NOT NULL,
  `station` int(11) NOT NULL,
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `registration` (
  `id` int(11) NOT NULL,
  `blindKey` blob NOT NULL,
  `encryptedVote` blob NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `results` (
  `proposal` int(11) NOT NULL,
  `answer` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `count` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stations` (
  `id` int(11) NOT NULL,
  `proposal` int(11) NOT NULL,
  `registrations_count` int(11) NOT NULL,
  `ballots_count` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `webservice` (
  `id` int(11) NOT NULL,
  `type` enum('app', 'judge', 'notary', 'station') NOT NULL,
  `key` blob NOT NULL,
  `url` varchar(2048) CHARACTER SET ascii COLLATE ascii_bin NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `area`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `ballot`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `ballots`
  ADD KEY `proposal` (`proposal`),
  ADD KEY `station` (`station`);

ALTER TABLE `citizen`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `corpus`
  ADD KEY `proposal` (`proposal`),
  ADD KEY `citizen` (`citizen`);

ALTER TABLE `endorsement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `endorsedSignature` (`endorsedSignature`);

ALTER TABLE `participation`
  ADD PRIMARY KEY `id`,
  ADD KEY `station` (`station`),
  ADD KEY `referendum` (`referendum`);

ALTER TABLE `publication`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `signature` (`signature`);
  ADD KEY `key` (`key`);

ALTER TABLE `proposal`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `registration`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `results`
  ADD KEY `proposal` (`proposal`);

ALTER TABLE `stations`
  ADD KEY `id` (`id`),
  ADD KEY `proposal` (`proposal`);

ALTER TABLE `webservice`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `webservice`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `area`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `publication`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

COMMIT;
