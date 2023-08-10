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
  `referendum` varchar(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `stationKey` varchar(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `stationSignature` varchar(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `revoke` tinyint(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ballots` (
  `referendum` int(11) NOT NULL,
  `station` int(11) NOT NULL,
  `key` varchar(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `revoke` tinyint(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `citizen` (
  `id` int(11) NOT NULL,
  `familyName` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `givenNames` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `picture` text CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `home` point NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `corpus` (
  `referendum` int(11) NOT NULL,
  `citizen` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `endorsement` (
  `id` int(11) NOT NULL,
  `publicationKey` varchar(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `publicationSignature` varchar(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `publicationFingerprint` varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `revoke` tinyint(1) NOT NULL,
  `message` varchar(2048) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comment` varchar(2048) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `participation` (
  `referendum` int(11) NOT NULL,
  `count` int(11) NOT NULL,
  `corpus` int(11) NOT NULL,
  `registrations` int(11) NOT NULL,
  `rejected` int(11) NOT NULL,
  `void` int(11) NOT NULL,
  `updated` timestamp NOT NULL,
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `publication` (
  `id` int(11) NOT NULL,
  `schema` varchar(256) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `key` varchar(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `signature` varchar(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `fingerprint` varchar(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'sha1 of signature',
  `published` bigint(15) NOT NULL,
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `proposal` (
  `id` int(11) NOT NULL,
  `trustee` varchar(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `area` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `question` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `answers` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret` tinyint(1) NOT NULL
  `deadline` bigint(15) NOT NULL,
  `website` varchar(2048) CHARACTER SET ascii COLLATE ascii_bin NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `registration` (
  `id` int(11) NOT NULL,
  `proposal` varchar(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `stationKey` varchar(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `stationSignature` varchar(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `revoke` tinyint(1) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `registrations` (
  `proposal` int(11) NOT NULL,
  `station` int(11) NOT NULL,
  `citizen` int(11) NOT NULL,
  `published` bigint(15) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `results` (
  `proposal` int(11) NOT NULL,
  `answer` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `count` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `station` (
  `id` int(11) NOT NULL,
  `key` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(2048) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `stations` (
  `id` int(11) NOT NULL,
  `proposal` int(11) NOT NULL,
  `registrations_count` int(11) NOT NULL,
  `ballots_count` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `trustee` (
  `id` int(11) NOT NULL,
  `key` varchar(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
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
  ADD KEY `publicationFingerprint` (`publicationFingerprint`);

ALTER TABLE `participation`
  ADD PRIMARY KEY `proposal` (`proposal`);

ALTER TABLE `publication`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `fingerprint` (`fingerprint`);

ALTER TABLE `proposal`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `registration`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `registrations`
  ADD KEY `proposal` (`proposal`),
  ADD KEY `station` (`station`),
  ADD KEY `citizen` (`citizen`);

ALTER TABLE `results`
  ADD KEY `proposal` (`proposal`);

ALTER TABLE `station`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `stations`
  ADD KEY `id` (`id`),
  ADD KEY `proposal` (`proposal`);

ALTER TABLE `trustee`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `area`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `publication`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `station`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `trustee`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;
