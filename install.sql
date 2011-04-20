DROP TABLE IF EXISTS `%TABLE_PREFIX%wv16_groups`;
DROP TABLE IF EXISTS `%TABLE_PREFIX%wv16_user_groups`;
DROP TABLE IF EXISTS `%TABLE_PREFIX%wv16_user_values`;
DROP TABLE IF EXISTS `%TABLE_PREFIX%wv16_users`;

CREATE TABLE `%TABLE_PREFIX%wv16_groups` (
  `name`  VARCHAR(96)  NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  PRIMARY KEY (`name`(96))
) ENGINE = InnoDB;

CREATE TABLE `%TABLE_PREFIX%wv16_user_groups` (
  `user_id`  SMALLINT(5) UNSIGNED NOT NULL,
  `group`    VARCHAR(96)          NOT NULL,
  PRIMARY KEY (`user_id`, `group`(96)),
  KEY `user_id` (`user_id`)
) ENGINE = InnoDB;

CREATE TABLE `%TABLE_PREFIX%wv16_user_values` (
  `user_id`    INT(10) UNSIGNED NOT NULL,
  `attribute`  VARCHAR(96)      NOT NULL,
  `set_id`     SMALLINT(5)      NOT NULL DEFAULT '1',
  `value`      TEXT,
  PRIMARY KEY (`user_id`, `attribute`(96), `set_id`)
) ENGINE = InnoDB;

CREATE TABLE `%TABLE_PREFIX%wv16_users` (
  `id`                 INT(10) UNSIGNED    NOT NULL AUTO_INCREMENT,
  `login`              VARCHAR(100)        NOT NULL,
  `password`           VARCHAR(40)         NOT NULL,
  `registered`         DATETIME            NOT NULL,
  `type`               VARCHAR(96)         NOT NULL DEFAULT 'default',
  `deleted`            TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  `activated`          TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  `confirmed`          TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  `was_activated`      TINYINT(1) UNSIGNED NOT NULL DEFAULT '0',
  `confirmation_code`  VARCHAR(20)         NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `login` (`login`)
) ENGINE = InnoDB;
