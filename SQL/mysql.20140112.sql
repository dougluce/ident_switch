CREATE TABLE IF NOT EXISTS `identities_imap_hosts` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `domain` varchar(255) COLLATE utf8_general_ci NOT NULL,
  `host` varchar(255) COLLATE utf8_general_ci DEFAULT NULL,
  `ts` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1 ;

UPDATE `system` SET `value`='initial|20140112' WHERE `name`='myrc_identities_imap';