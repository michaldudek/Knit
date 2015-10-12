# Dump of table elves
# ------------------------------------------------------------

DROP TABLE IF EXISTS `elves`;

CREATE TABLE `elves` (
  `name` varchar(64) NOT NULL DEFAULT '',
  `place` varchar(64) NOT NULL DEFAULT '',
  PRIMARY KEY (`name`,`place`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



# Dump of table hobbits
# ------------------------------------------------------------

DROP TABLE IF EXISTS `hobbits`;

CREATE TABLE `hobbits` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `height` int(11) DEFAULT NULL,
  `surname` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
