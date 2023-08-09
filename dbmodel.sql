
-- ------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- Wolves implementation : © <Your name here> <Your email address here>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

CREATE TABLE IF NOT EXISTS `regions`(
    `region_id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tile_number` TINYINT NOT NULL,
    `center_x` TINYINT NOT NULL,
    `center_y` TINYINT NOT NULL,
    `rotated` TINYINT NOT NULL,
    `moon_phase` TINYINT NOT NULL,
    PRIMARY KEY (`region_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `land`(
    `x` TINYINT NOT NULL,
    `y` TINYINT NOT NULL,
    `terrain` TINYINT NOT NULL COMMENT 'T_* value',
    `region_id` TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (`x`, `y`),
    FOREIGN KEY (`region_id`) REFERENCES `regions`(`region_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `pieces`(
    `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `owner` INT UNSIGNED NULL COMMENT 'Owner player ID',
    `kind` TINYINT NOT NULL COMMENT 'P_* value',
    `x` TINYINT NOT NULL,
    `y` TINYINT NOT NULL,
    `prey_metadata` TINYINT,
    `ai` BOOLEAN DEFAULT FALSE,
    PRIMARY KEY (`id`),
    FOREIGN KEY (`x`, `y`) REFERENCES `land`(`x`, `y`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `player_status`(
    `player_id` INT UNSIGNED NOT NULL,
    `home_terrain` TINYINT NOT NULL,
    `deployed_howl_dens` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `deployed_pack_dens` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `deployed_speed_dens` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `deployed_lairs` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `deployed_wolves` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `terrain_tokens` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `turn_tokens` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `prey_data` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `tile_0` TINYINT UNSIGNED NOT NULL DEFAULT 0, 
    `tile_1` TINYINT UNSIGNED NOT NULL DEFAULT 0, 
    `tile_2` TINYINT UNSIGNED NOT NULL DEFAULT 0, 
    `tile_3` TINYINT UNSIGNED NOT NULL DEFAULT 0, 
    `tile_4` TINYINT UNSIGNED NOT NULL DEFAULT 0, 
    PRIMARY KEY (`player_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `moonlight_board`(
    `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kind` TINYINT UNSIGNED NOT NULL,
    `player_id` INT UNSIGNED,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `score_token`(
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_id` INT NOT NULL,
    `type` TINYINT NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `turn_log`(
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `turn` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `turn_index` (`turn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;

CREATE TABLE IF NOT EXISTS `turn_action`(
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `data` JSON,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;