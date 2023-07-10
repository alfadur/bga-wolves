
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

CREATE TABLE IF NOT EXISTS `player_status`(
    `player_id` INT UNSIGNED NOT NULL,
    `home_terrain` TINYINT NOT NULL,
    `deployed_howl_dens` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `deployed_pack_dens` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `deployed_wolf_dens` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `deployed_lairs` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `deployed_wolves` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `terrain_tokens` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `turn_tokens` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `player_tiles`( 
    `player_id` INT UNSIGNED NOT NULL, 
    `0` TINYINT UNSIGNED NOT NULL DEFAULT 0, 
    `1` TINYINT UNSIGNED NOT NULL DEFAULT 0, 
    `2` TINYINT UNSIGNED NOT NULL DEFAULT 0, 
    `3` TINYINT UNSIGNED NOT NULL DEFAULT 0, 
    `4` TINYINT UNSIGNED NOT NULL DEFAULT 0, 
    PRIMARY KEY (`player_id`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `moonlight_board`(
    `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kind` TINYINT UNSIGNED NOT NULL,
    `player_id` INT UNSIGNED,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `score_token`(
    `player_id` INT NOT NULL,
    `type` TINYINT NOT NULL,
    PRIMARY KEY (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8

CREATE TABLE IF NOT EXISTS `turn_log`(
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `turn_id` INT UNSIGNED NOT NULL,
    `prev_move_id` INT UNSIGNED NOT NULL,
    `next_move_id` INT UNSIGNED NOT NULL,
    `action_info` JSON NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `turn_id` (`turn_id`),
    FOREIGN KEY (`prev_move_id`) REFERENCES `turn_log`(`id`),
    FOREIGN KEY (`next_move_id`) REFERENCES `turn_log`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;