DROP TABLE IF EXISTS `%TABLE_PREFIX%wv16_attributes`;
DROP TABLE IF EXISTS `%TABLE_PREFIX%wv16_groups`;
DROP TABLE IF EXISTS `%TABLE_PREFIX%wv16_user_groups`;
DROP TABLE IF EXISTS `%TABLE_PREFIX%wv16_user_values`;
DROP TABLE IF EXISTS `%TABLE_PREFIX%wv16_users`;
DROP TABLE IF EXISTS `%TABLE_PREFIX%wv16_utype_attrib`;
DROP TABLE IF EXISTS `%TABLE_PREFIX%wv16_utypes`;

CREATE TABLE `%TABLE_PREFIX%wv16_attributes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `title` varchar(200) NOT NULL,
  `helptext` varchar(1024) NOT NULL DEFAULT '',
  `position` int(10) unsigned NOT NULL,
  `datatype` int(2) unsigned NOT NULL,
  `params` text NOT NULL,
  `default_value` text NOT NULL,
  `hidden` tinyint(1) unsigned not null default '0',
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE = InnoDB;

INSERT INTO `%TABLE_PREFIX%wv16_attributes` (`id`, `name`, `title`, `helptext`, `position`, `datatype`, `params`, `default_value`, `hidden`, `deleted`) VALUES (1,'firstname','Vorname','',1,1,'0|65535','',0,0);
INSERT INTO `%TABLE_PREFIX%wv16_attributes` (`id`, `name`, `title`, `helptext`, `position`, `datatype`, `params`, `default_value`, `hidden`, `deleted`) VALUES (2,'email','E-Mail','',2,1,'0|65535','',0,0);

CREATE TABLE `%TABLE_PREFIX%wv16_groups` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` smallint(5) unsigned NOT NULL DEFAULT '0',
  `name` varchar(200) DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`)
) ENGINE = InnoDB;

CREATE TABLE `%TABLE_PREFIX%wv16_user_groups` (
  `user_id` smallint(5) unsigned NOT NULL,
  `group_id` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`group_id`),
  KEY `user_id` (`user_id`),
  KEY `group_id` (`group_id`)
) ENGINE = InnoDB;

CREATE TABLE `%TABLE_PREFIX%wv16_user_values` (
  `user_id` int(10) unsigned NOT NULL,
  `attribute_id` int(10) unsigned NOT NULL,
  `set_id` smallint(5) NOT NULL DEFAULT '1' COMMENT 'negative Werte bedeuten, dass dieses Set nicht mehr geändert werden darf',
  `value` text NOT NULL,
  PRIMARY KEY (`user_id`,`attribute_id`,`set_id`)
) ENGINE = InnoDB;

CREATE TABLE `%TABLE_PREFIX%wv16_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `login` varchar(100) NOT NULL,
  `password` varchar(40) NOT NULL,
  `registered` datetime NOT NULL,
  `type_id` smallint(5) unsigned NOT NULL DEFAULT '1',
  `deleted` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `activated` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `confirmed` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `was_activated` tinyint(1) unsigned NOT NULL DEFAULT '0',
  `confirmation_code` varchar(20) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE = InnoDB;

CREATE TABLE `%TABLE_PREFIX%wv16_utype_attrib` (
  `user_type` int(5) unsigned NOT NULL,
  `attribute_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`user_type`,`attribute_id`)
) ENGINE = InnoDB;

INSERT INTO `%TABLE_PREFIX%wv16_utype_attrib` (`user_type`, `attribute_id`) VALUES (1,1), (1,2);

CREATE TABLE `%TABLE_PREFIX%wv16_utypes` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL DEFAULT '',
  `title` varchar(200) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE = InnoDB;

INSERT INTO `%TABLE_PREFIX%wv16_utypes` (`id`, `name`, `title`) VALUES ('1','default','Standardbenutzer');
