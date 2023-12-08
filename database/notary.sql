SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `area` (
  `id` int(11) NOT NULL,
  `name` varchar(1024) COLLATE utf8mb4_unicode_ci NOT NULL,
  `polygons` multipolygon NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `citizen` (
  `id` int(11) NOT NULL,
  `appKey` blob NOT NULL,
  `appSignature` blob NOT NULL,
  `givenNames` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `familyName` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `picture` blob NOT NULL,
  `home` point NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `endorsement` (
  `id` int(11) NOT NULL,
  `appKey` blob NOT NULL,
  `appSignature` blob NOT NULL,
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
  `type` enum('citizen','endorsement','area','proposal','participation','vote') NOT NULL,
  `published` datetime NOT NULL,
  `signature` blob NOT NULL COMMENT 'signature of the publication by the author',
  `key` blob NOT NULL COMMENT 'public key of author'
  `signatureSHA1` binary(20) GENERATED ALWAYS AS (unhex(sha(`signature`))) STORED,
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `proposal` (
  `id` int(11) NOT NULL,
  `area` blob NOT NULL,
  `title` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `question` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `answers` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret` tinyint(1) NOT NULL,
  `deadline` datetime NOT NULL,
  `website` varchar(2048) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `participants` int(11) NOT NULL,
  `corpus` int(11) NOT NULL,
  `results` datetime NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `participation` (
  `id` int(11) NOT NULL,
  `appKey` blob NOT NULL,
  `appSignature` blob NOT NULL,
  `referendum` blob NOT NULL,
  `encryptedVote` blob NOT NULL,
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vote` (
  `id` int(11) NOT NULL,
  `appKey` blob NOT NULL,
  `appSignature` blob NOT NULL,
  `referendum` blob NOT NULL,
  `number` int(11) NOT NULL,
  `ballot` binary(32) NOT NULL,
  `answer` text COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `results` (
  `referendum` int(11) NOT NULL,
  `answer` varchar(256) COLLATE utf8mb4_unicode_ci NOT NULL,
  `count` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `webservice` (
  `id` int(11) NOT NULL,
  `type` enum('app', 'judge', 'notary', 'station') NOT NULL,
  `key` blob NOT NULL,
  `url` varchar(2048) CHARACTER SET ascii COLLATE ascii_bin NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `area`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `citizen`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `endorsement`
  ADD PRIMARY KEY (`id`),
  ADD KEY `endorsedSignature` (`endorsedSignature`);

ALTER TABLE `participation`
  ADD PRIMARY KEY `id`,
  ADD KEY `referendum` (`referendum`);

ALTER TABLE `vote`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `referendum` (`referendum`,`ballot`) USING HASH;

ALTER TABLE `vote`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `publication`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `signatureSHA1` (`signatureSHA1`),
  ADD KEY `key` (`key`);

ALTER TABLE `proposal`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `results`
  ADD UNIQUE KEY `referendum` (`referendum`,`answer`) USING HASH;

ALTER TABLE `webservice`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `webservice`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `area`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `publication`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

COMMIT;
