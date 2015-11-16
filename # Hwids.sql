CREATE TABLE IF NOT EXISTS `hwids` (
  `login` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `hwid` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `banned` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

ALTER TABLE `hwids`
  ADD UNIQUE KEY `login_hwid_pair` (`login`,`hwid`);
