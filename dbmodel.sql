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
CREATE TABLE IF NOT EXISTS `tile` (
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
CREATE TABLE IF NOT EXISTS `map` (
    `id` varchar(10) NOT NULL,
    `x` int(10) NOT NULL,
    `y` int(10) NOT NULL,
    `rotate` int(10) NOT NULL,
    `fire` int(10) DEFAULT 0,
    `fire_color` varchar(10) NOT NULL,
    `has_trapdoor` int(1) DEFAULT 0,
    `deckhand` int(10) DEFAULT 0,
    `explosion` int(10) DEFAULT NULL,
    `exploded` int(1) DEFAULT 0,
    `destroyed` int(1) DEFAULT 0,
    `escape` int(1) DEFAULT 0,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;
CREATE TABLE IF NOT EXISTS `character` (
    `character_id` varchar(10) NOT NULL,
    `player_id` int(10) unsigned NOT NULL,
    `necromancer_player_id` int(10) unsigned NULL,
    `order` int(10) UNSIGNED DEFAULT 0,
    `item` varchar(20) DEFAULT NULL,
    `actions` int(10) UNSIGNED DEFAULT 0,
    `fatigue` int(10) UNSIGNED DEFAULT 0,
    `tempStrength` int(10) UNSIGNED DEFAULT 0,
    `confirmed` int(1) UNSIGNED DEFAULT 0,
    FOREIGN KEY (player_id) REFERENCES player(player_id),
    FOREIGN KEY (necromancer_player_id) REFERENCES player(player_id),
    PRIMARY KEY (`character_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;
CREATE TABLE IF NOT EXISTS `undoState` (
    `undo_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `character_id` varchar(10) NOT NULL,
    `gamelog_move_id` int(10) unsigned NULL,
    `pending` int(1) UNSIGNED DEFAULT 0,
    `characterTable` text DEFAULT '',
    `globalsTable` text DEFAULT '',
    `extraTables` text DEFAULT '',
    PRIMARY KEY (`undo_id`)
) ENGINE = InnoDB DEFAULT CHARSET = utf8;