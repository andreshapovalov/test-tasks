CREATE DATABASE IF NOT EXISTS `ash-demo-db`;

USE `ash-demo-db`;

CREATE TABLE IF NOT EXISTS `users` (
  `id`    INT(11)      NOT NULL AUTO_INCREMENT,
  `name`  VARCHAR(255) NOT NULL,
  `email` VARCHAR(50)  NOT NULL,
  `age`   TINYINT               DEFAULT 0,
  PRIMARY KEY (`id`)
)
  ENGINE = InnoDB
  DEFAULT CHARSET = utf8;