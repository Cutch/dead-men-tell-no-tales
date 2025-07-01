-- ------
-- BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
-- deadmentellnotales implementation : © Cutch <Your email address here>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----
-- dbmodel.sql
-- This is the file where you are describing the database schema of your game
-- Basically, you just have to export from PhpMyAdmin your table structure and copy/paste
-- this export here.
-- Note that the database itself and the standard tables ("global", "stats", "gamelog" and "player") are
-- already created and must not be created here
-- Note: The database schema is created from this file when the game starts. If you modify this file,
--       you have to restart a game to see your changes in database.
-- Example 1: create a standard "card" table to be used with the "Deck" tools (see example game "hearts"):
CREATE TABLE IF NOT EXISTS `tiles` (
    `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `card_type` varchar(20) NOT NULL,
    `card_type_arg` varchar(20) NOT NULL,
    `card_location` varchar(16) NOT NULL,
    `card_location_arg` int(11) NOT NULL,
    PRIMARY KEY (`card_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8 AUTO_INCREMENT = 1;
CREATE TABLE IF NOT EXISTS `revenge` (
    `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `card_type` varchar(20) NOT NULL,
    `card_type_arg` varchar(20) NOT NULL,
    `card_location` varchar(16) NOT NULL,
    `card_location_arg` int(11) NOT NULL,
    PRIMARY KEY (`card_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8 AUTO_INCREMENT = 1;
CREATE TABLE IF NOT EXISTS `item` (
    `item_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `item_name` varchar(20) NOT NULL,
    PRIMARY KEY (`item_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;
CREATE TABLE IF NOT EXISTS `character` (
    `character_name` varchar(10) NOT NULL,
    `player_id` int(10) unsigned NOT NULL,
    `necromancer_player_id` int(10) unsigned NULL,
    `order` int(10) UNSIGNED DEFAULT 0,
    `item` int(10) unsigned NULL,
    `action` int(10) UNSIGNED DEFAULT 0,
    `fatigue` int(10) UNSIGNED DEFAULT 0,
    `tempStrength` int(10) UNSIGNED DEFAULT 0,
    `confirmed` int(1) UNSIGNED DEFAULT 0,
    FOREIGN KEY (item) REFERENCES item(item_id),
    FOREIGN KEY (player_id) REFERENCES player(player_id),
    FOREIGN KEY (necromancer_player_id) REFERENCES player(player_id),
    PRIMARY KEY (`character_name`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;
CREATE TABLE IF NOT EXISTS `undoState` (
    `undo_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `character_name` varchar(10) NOT NULL,
    `gamelog_move_id` int(10) unsigned NULL,
    `pending` int(1) UNSIGNED DEFAULT 0,
    `itemTable` text DEFAULT '',
    `characterTable` text DEFAULT '',
    `globalsTable` text DEFAULT '',
    `extraTables` text DEFAULT '',
    PRIMARY KEY (`undo_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;