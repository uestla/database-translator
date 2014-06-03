DROP TABLE IF EXISTS `lang`;
CREATE TABLE `lang` (
  `shortcut` varchar(2) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`shortcut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;


DROP TABLE IF EXISTS `translation`;
CREATE TABLE `translation` (
  `idf` varchar(255) NOT NULL COMMENT 'translation identifier',
  `lang` varchar(2) NOT NULL,
  `amount_type` enum('1','2','3') NOT NULL DEFAULT '1' COMMENT '[1] => [one], [2] => [two, three, four], [3] => [rest...]',
  `translation` varchar(255) NOT NULL,
  PRIMARY KEY (`idf`,`lang`,`amount_type`),
  KEY `lang` (`lang`),
  CONSTRAINT `translation_ibfk_2` FOREIGN KEY (`lang`) REFERENCES `lang` (`shortcut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
