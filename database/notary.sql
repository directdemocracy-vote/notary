SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE TABLE `area` (
  `publication` int(11) NOT NULL,
  `id` int(11) NOT NULL,
  `name` varchar(1024) NOT NULL,
  `polygons` multipolygon NOT NULL,
  `local` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `certificate` (
  `publication` int(11) NOT NULL,
  `app` int(11) NOT NULL,
  `appSignature` blob NOT NULL,
  `type` enum('endorse','report','update','transfer','lost','sign','trust','distrust') NOT NULL,
  `certifiedPublication` int(11) NOT NULL,
  `comment` varchar(2048) NOT NULL,
  `message` varchar(2048) NOT NULL,
  `latest` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `citizen` (
  `publication` int(11) NOT NULL,
  `app` int(11) NOT NULL,
  `appSignature` blob NOT NULL,
  `givenNames` varchar(256) NOT NULL,
  `familyName` varchar(256) NOT NULL,
  `picture` blob NOT NULL,
  `home` point NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `participant` (
  `id` int(11) NOT NULL,
  `type` enum('app','citizen','judge','notary','station','none') NOT NULL,
  `key` blob NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `participant` (`id`, `type`, `key`) VALUES
(0, 'none', ''),
(1, 'app', 0xbc3db4410d7cbbbeb579a9f5fb382a943168e87d849b0de63e6071794db8c78a35336b5c1acf90ec6eb101245fe0b99274ed61ebb64dd2ccb5b5ab0d1c7f0893808deb77a5063e042d4ef4c597985c8331c710ea8ac16001740edf895cd8416dfb02acf933617fc0ab0ee7b1788579778631b6dc29613ffa436c2bb9d4e6e708de2b454876d5c7354e55d3529ac659a85e7cb66ac5887a37007f11d848a161cdf9a45e256a16b43904701ffbf45e6277dcfb1ecec2b9ba134379d6f5074b157237f4c2528034da1e54b2ebcc291e0eb649596ec2637abc33f928d5ef9efc1f9153874134b1aef3609b87698a8f7afbf045e5ee82012c7f2b4ffe3d81dc881aad),
(2, 'app', 0x9d1844911a38eef4f6666e02aaecdabf287e4bfc8592cbd9875795db46dc83e61c09fc3335dbcf46cfb95a21261387ae8ee18f9245c6eaefc39664177f6b3330c53018292a2523e2e9f6bdd2940ac7cd501d8f006f8cfaf4f9ef063b7cb6df0bf3ed0d4463a92a49acc33697e0e3778a9941c78eb2127d340d0f56d90ce5d84c1a20f958361904b36e11b79cd07865313061f2d5e491fa6478360aa9d4b5c2c86c5b5c9f01fc1e61578408a2bed4dab6825454b2eec365d3cd3134bb98d248c7095f8737487811a45d476152c3dbcccb0e542665a90851f24256b8d84835171c24f4890784d7bf5a86ce4f5262cb7531cfd9d47a80906b900e3405ce68edb0b5),
(3, 'notary', 0xd5bf5f591035f4b9f433fe09bb035afa14e6fa34af68bef138317239cd1762a338024f6ad73478169dc64b4e13f12ab95a4acfd9447d7ed0ad56f93dc521a32daeb51a2d3ede1ef8d623db6ee48952784a4aa381918fa967abf400ed4d0896cc8e14034908abb1c9bd8b3153a17b45330bffebbebf10d11c7840f58b0990227e29847ca6bff29cb260c38539aff023bbe06e2ec121545c3f51a097bd7618de8141d4eb8777de8604a0a2933e0cf15057feac851f48d28b8d4b639a683b6d61f8c44f0f900e3010bc573350b186c08c437107a73dbbb702ee4de6aad4a0489f4c365e642ba7c3a81653f77e9d8b5e4cf4e86587f93cc4d100ee9e43ec89dec121),
(4, 'judge', 0xc22af5662b5eeddea901bb9a88848573eff307587ff19637825d360e000f2843aca589b26ef3ca135c86c0d4f915a1cd523b107c6ea7e40a10999943afebf90723624880dd39801000b137841578d41fe1fe688c41232e31bf95a1dbae89786cbeb7d92da8ede44e940e3dbd127074cf427e05232a113d6a0e18bcd08de60084b95afbd2c385123b1772295e747e8ca6cdf1a53f6718c9802d1738d8c8b9ae3917010b5d0664cddd12b6944df4593c6c7c435c14586cb99a23a5923915a9adae725ef6803eca06792a8cf5bdaf7fe0d55924cf3580d5b11bc3019d2c1547858d6be357560ed47b0b162d4998d48d145438b08870c16e686b3acd569dfb45bebb),
(5, 'station', 0xc27b5858dc6c0126006d48dba2d3a64488cb584040426a13dbebf64d160faffb678b9e7f6d3e9c3e185fb82f6c67d6abca22a7f3eb61a31f8b4d587c6210550610282362bf558a2786696ef47be10fbe8bb14bea832a421053482d2e69bf47410ba20bcc82dbf11c08203ad363a4186ae30df4ce48f140bc8da643ce84782034e50d9f10076e87df2206453799fbed36811b1774b67f630ffb82f7c95ee325ca1d7d618a13860f6e2950f19040985b078f124327ed530dd29429e10f35eb8607c0f91894ce381f0597aad4389b5ca9eb6717e116e1b65c75a10ccdbe4b9f0a836b53308725b71daa7e3e6c26e7b335153b5216c7d764eab7b35dcb95f08b166b);

CREATE TABLE `participation` (
  `publication` int(11) NOT NULL,
  `app` int(11) NOT NULL,
  `appSignature` blob NOT NULL,
  `referendum` int(11) NOT NULL,
  `encryptedVote` blob NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `proposal` (
  `publication` int(11) NOT NULL,
  `area` int(11) NOT NULL,
  `title` varchar(128) NOT NULL,
  `description` text NOT NULL,
  `question` varchar(128) NOT NULL,
  `answers` text NOT NULL,
  `type` enum('petition','referendum','election') NOT NULL,
  `secret` tinyint(1) NOT NULL,
  `deadline` datetime NOT NULL,
  `trust` bigint(20) NOT NULL,
  `website` varchar(2048) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `participants` int(11) NOT NULL,
  `corpus` int(11) NOT NULL,
  `results` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `publication` (
  `id` int(11) NOT NULL,
  `version` smallint(6) NOT NULL,
  `type` enum('citizen','certificate','area','proposal','participation','vote') NOT NULL,
  `published` datetime NOT NULL,
  `signature` blob NOT NULL COMMENT 'signature of the publication by the author',
  `signatureSHA1` binary(20) GENERATED ALWAYS AS (unhex(sha(`signature`))) STORED,
  `participant` int(11) NOT NULL COMMENT 'participant id of the author'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `results` (
  `referendum` int(11) NOT NULL,
  `answer` text NOT NULL,
  `count` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `vote` (
  `publication` int(11) NOT NULL,
  `app` int(11) NOT NULL,
  `appSignature` blob NOT NULL,
  `referendum` int(11) NOT NULL,
  `number` int(11) NOT NULL,
  `ballot` binary(32) NOT NULL,
  `answer` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `webservice` (
  `participant` int(11) NOT NULL,
  `url` varchar(2048) CHARACTER SET ascii COLLATE ascii_bin NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `webservice` (`participant`, `url`) VALUES
(1, 'https://app.directdemocracy.vote'),
(2, 'https://app.directdemocracy.vote/test'),
(3, 'https://notary.directdemocracy.vote'),
(4, 'https://judge.directdemocracy.vote'),
(5, 'https://station.directdemocracy.vote');


ALTER TABLE `area`
  ADD PRIMARY KEY (`publication`);
  ADD KEY `id` (`id`);

ALTER TABLE `certificate`
  ADD PRIMARY KEY (`publication`),
  ADD KEY `publicationId` (`certifiedPublication`),
  ADD KEY `app` (`app`);

ALTER TABLE `citizen`
  ADD PRIMARY KEY (`publication`),
  ADD KEY `app` (`app`);

ALTER TABLE `participant`
  ADD PRIMARY KEY (`id`);

ALTER TABLE `participation`
  ADD PRIMARY KEY (`publication`),
  ADD KEY `app` (`app`),
  ADD KEY `referendum` (`referendum`);

ALTER TABLE `proposal`
  ADD PRIMARY KEY (`publication`),
  ADD KEY `area` (`area`);

ALTER TABLE `publication`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `signatureSHA1` (`signatureSHA1`),
  ADD KEY `authorId` (`participant`);

ALTER TABLE `results`
  ADD UNIQUE KEY `referendum` (`referendum`,`answer`) USING HASH;

ALTER TABLE `vote`
  ADD PRIMARY KEY (`publication`),
  ADD KEY `app` (`app`),
  ADD KEY `referendum` (`referendum`);

ALTER TABLE `webservice`
  ADD PRIMARY KEY (`participant`);


ALTER TABLE `participant`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

ALTER TABLE `publication`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;


ALTER TABLE `area`
  ADD CONSTRAINT `area` FOREIGN KEY (`publication`) REFERENCES `publication` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;

ALTER TABLE `certificate`
  ADD CONSTRAINT `certificate` FOREIGN KEY (`publication`) REFERENCES `publication` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `certificateApp` FOREIGN KEY (`app`) REFERENCES `participant` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `certifiedPublication` FOREIGN KEY (`certifiedPublication`) REFERENCES `publication` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `citizen`
  ADD CONSTRAINT `citizen` FOREIGN KEY (`publication`) REFERENCES `publication` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `citizenApp` FOREIGN KEY (`app`) REFERENCES `participant` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `participation`
  ADD CONSTRAINT `participation` FOREIGN KEY (`publication`) REFERENCES `publication` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `participationApp` FOREIGN KEY (`app`) REFERENCES `participant` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `referendumParticipation` FOREIGN KEY (`referendum`) REFERENCES `proposal` (`publication`) ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `proposal`
  ADD CONSTRAINT `proposal` FOREIGN KEY (`publication`) REFERENCES `publication` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `proposalArea` FOREIGN KEY (`area`) REFERENCES `area` (`publication`) ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `publication`
  ADD CONSTRAINT `publication` FOREIGN KEY (`participant`) REFERENCES `participant` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `vote`
  ADD CONSTRAINT `vote` FOREIGN KEY (`publication`) REFERENCES `publication` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  ADD CONSTRAINT `voteApp` FOREIGN KEY (`app`) REFERENCES `participant` (`id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  ADD CONSTRAINT `voteReferendum` FOREIGN KEY (`referendum`) REFERENCES `proposal` (`publication`) ON DELETE NO ACTION ON UPDATE NO ACTION;

ALTER TABLE `webservice`
  ADD CONSTRAINT `webservice` FOREIGN KEY (`participant`) REFERENCES `participant` (`id`) ON DELETE CASCADE ON UPDATE NO ACTION;
COMMIT;
