SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `ballot` (
  `id` int(11) NOT NULL,
  `referendum` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stationKey` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `stationSignature` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `citizenKey` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `citizenSignature` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `citizen` (
  `id` int(11) NOT NULL,
  `familyName` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `givenNames` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `picture` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `latitude` int(11) NOT NULL,
  `longitude` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `endorsement` (
  `id` int(11) NOT NULL,
  `publicationKey` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `publicationSignature` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `publicationFingerprint` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `revoke` tinyint(1) NOT NULL,
  `message` varchar(2048) COLLATE utf8mb4_unicode_ci NOT NULL,
  `comment` varchar(2048) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `publication` (
  `id` int(11) NOT NULL,
  `schema` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `signature` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fingerprint` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'sha1 of signature',
  `published` bigint(15) NOT NULL,
  `expires` bigint(15) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `referendum` (
  `id` int(11) NOT NULL,
  `trustee` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL,
  `area` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `title` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `question` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `answers` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `deadline` bigint(15) NOT NULL,
  `website` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `trustee` (
  `id` int(11) NOT NULL,
  `url` varchar(2048) COLLATE utf8mb4_unicode_ci NOT NULL,
  `key` varchar(512) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `ballot`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `citizen`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `endorsement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `publicationFingerprint` (`publicationFingerprint`);

ALTER TABLE `publication`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `fingerprint` (`fingerprint`);

ALTER TABLE `referendum`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `trustee`
  ADD PRIMARY KEY (`id`),
  ADD KEY `key` (`key`(250));

ALTER TABLE `publication`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `trustee`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;
