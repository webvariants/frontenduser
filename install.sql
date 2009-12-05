DROP TABLE IF EXISTS `%TABLE_PREFIX%wv16_attributes`;
DROP TABLE IF EXISTS `%TABLE_PREFIX%wv16_groups`;
DROP TABLE IF EXISTS `%TABLE_PREFIX%wv16_rights`;
DROP TABLE IF EXISTS `%TABLE_PREFIX%wv16_user_groups`;
DROP TABLE IF EXISTS `%TABLE_PREFIX%wv16_user_values`;
DROP TABLE IF EXISTS `%TABLE_PREFIX%wv16_users`;
DROP TABLE IF EXISTS `%TABLE_PREFIX%wv16_utype_attrib`;
DROP TABLE IF EXISTS `%TABLE_PREFIX%wv16_utypes`;

CREATE TABLE `%TABLE_PREFIX%wv16_attributes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL,
  `title` varchar(200) NOT NULL,
  `position` int(10) unsigned NOT NULL,
  `datatype` int(2) unsigned NOT NULL,
  `params` text NOT NULL,
  `default_value` text NOT NULL,
  PRIMARY KEY (`id`)
);

INSERT INTO `%TABLE_PREFIX%wv16_attributes` (`id`, `name`, `title`, `position`, `datatype`, `params`, `default_value`) VALUES ('1','firstname','Vorname','1','2','0|65535','');

CREATE TABLE `%TABLE_PREFIX%wv16_groups` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` smallint(5) unsigned NOT NULL DEFAULT '0',
  `name` varchar(200) DEFAULT NULL,
  `title` varchar(200) DEFAULT NULL,
  `internal` tinyint(1) unsigned not null default '0',
  PRIMARY KEY (`id`),
  KEY `parent_id` (`parent_id`)
);

INSERT INTO `%TABLE_PREFIX%wv16_groups` (`id`, `parent_id`, `name`, `title`, `internal`) VALUES ('1','0','unconfirmed','Unbest�tigt','1');
INSERT INTO `%TABLE_PREFIX%wv16_groups` (`id`, `parent_id`, `name`, `title`, `internal`) VALUES ('2','0','confirmed','Best�tigt','1');
INSERT INTO `%TABLE_PREFIX%wv16_groups` (`id`, `parent_id`, `name`, `title`, `internal`) VALUES ('3','0','activated','Freigeschaltet','1');

CREATE TABLE `%TABLE_PREFIX%wv16_rights` (
  `group_id` smallint(5) unsigned NOT NULL,
  `object_id` smallint(6) unsigned NOT NULL,
  `object_type` tinyint(4) unsigned NOT NULL,
  `privilege` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`group_id`, `object_id`, `object_type`, `privilege`)
);

CREATE TABLE `%TABLE_PREFIX%wv16_user_groups` (
  `user_id` smallint(5) unsigned NOT NULL,
  `group_id` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`group_id`),
  KEY `user_id` (`user_id`),
  KEY `group_id` (`group_id`)
);

CREATE TABLE `%TABLE_PREFIX%wv16_user_values` (
  `user_id` int(10) unsigned NOT NULL,
  `attribute_id` int(10) unsigned NOT NULL,
  `value` text NOT NULL,
  PRIMARY KEY (`user_id`,`attribute_id`)
);

CREATE TABLE `%TABLE_PREFIX%wv16_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `login` varchar(100) NOT NULL,
  `password` varchar(40) NOT NULL,
  `registered` datetime NOT NULL,
  `type_id` smallint(5) unsigned NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `login` (`login`(100))
);

CREATE TABLE `%TABLE_PREFIX%wv16_utype_attrib` (
  `user_type` int(5) unsigned NOT NULL,
  `attribute_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`user_type`,`attribute_id`)
);

INSERT INTO `%TABLE_PREFIX%wv16_utype_attrib` (`user_type`, `attribute_id`) VALUES ('1','1');

CREATE TABLE `%TABLE_PREFIX%wv16_utypes` (
  `id` int(5) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(200) NOT NULL DEFAULT '',
  `title` varchar(200) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
);

INSERT INTO `%TABLE_PREFIX%wv16_utypes` (`id`, `name`, `title`) VALUES ('1','default','Standardbenutzer');
