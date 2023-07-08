
-- ------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- Wolves implementation : © <Your name here> <Your email address here>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

CREATE TABLE IF NOT EXISTS `land`(
    `x` TINYINT NOT NULL,
    `y` TINYINT NOT NULL,
    `terrain` TINYINT NOT NULL COMMENT 'T_* value',
    PRIMARY KEY (`x`, `y`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS pieces(
    `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `owner` INT NULL COMMENT 'Owner player ID',
    `kind` TINYINT NOT NULL COMMENT 'P_* value',
    `location` TINYINT NOT NULL COMMENT 'L_* value',
    `x` TINYINT NULL,
    `y` TINYINT NULL,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`x`, `y`) REFERENCES `land`(`x`, `y`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `player_tiles`( 
    `player_id` INT UNSIGNED NOT NULL, 
    `0` TINYINT UNSIGNED NOT NULL DEFAULT 0, 
    `1` TINYINT UNSIGNED NOT NULL DEFAULT 0, 
    `2` TINYINT UNSIGNED NOT NULL DEFAULT 0, 
    `3` TINYINT UNSIGNED NOT NULL DEFAULT 0, 
    `4` TINYINT UNSIGNED NOT NULL DEFAULT 0, 
    PRIMARY KEY (`player_id`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8;