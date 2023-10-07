<?php

/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Wolves implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * stats.inc.php
 *
 * Wolves game statistics description
 *
 */

/*
    In this file, you are describing game statistics, that will be displayed at the end of the
    game.
    
    !! After modifying this file, you must use "Reload  statistics configuration" in BGA Studio backoffice
    ("Control Panel" / "Manage Game" / "Your Game")
    
    There are 2 types of statistics:
    _ table statistics, that are not associated to a specific player (ie: 1 value for each game).
    _ player statistics, that are associated to each players (ie: 1 value for each player in the game).

    Statistics types can be "int" for integer, "float" for floating point values, and "bool" for boolean
    
    Once you defined your statistics there, you can start using "initStat", "setStat" and "incStat" method
    in your game logic, using statistics names defined below.
    
    !! It is not a good idea to modify this file when a game is running !!

    If your game is already public on BGA, please read the following before any change:
    http://en.doc.boardgamearena.com/Post-release_phase#Changes_that_breaks_the_games_in_progress
    
    Notes:
    * Statistic index is the reference used in setStat/incStat/initStat PHP method
    * Statistic index must contains alphanumerical characters and no space. Example: 'turn_played'
    * Statistics IDs must be >=10
    * Two table statistics can't share the same ID, two player statistics can't share the same ID
    * A table statistic can have the same ID than a player statistics
    * Statistics ID is the reference used by BGA website. If you change the ID, you lost all historical statistic data. Do NOT re-use an ID of a deleted statistic
    * Statistic name is the English description of the statistic as shown to players
    
*/
require_once('modules/constants.inc.php');

$stats_type = array(

    // Statistics global to table
    "table" => array(

        STAT_TURNS_TAKEN => array("id"=> 10,
                    "name" => totranslate("Number of turns"),
                    "type" => "int" ),
        STAT_BONUS_ACTIONS_TAKEN => [
            "id" => 11,
            "name" => totranslate("Number of bonus actions taken"),
            "type" => "int"
        ],
        STAT_TERRAIN_TOKENS_SPENT => [
            "id" => 12,
            "name" => totranslate("Number of bonus terrain tokens spent"),
            "type" => "int"
        ]

/*
        Examples:


        "table_teststat1" => array(   "id"=> 10,
                                "name" => totranslate("table test stat 1"), 
                                "type" => "int" ),
                                
        "table_teststat2" => array(   "id"=> 11,
                                "name" => totranslate("table test stat 2"), 
                                "type" => "float" )
*/  
    ),
    
    // Statistics existing for each player
    "player" => array(

        STAT_PLAYER_TURNS_PLAYED => array("id"=> 10,
                    "name" => totranslate("Turns played"),
                    "type" => "int" ),
        STAT_PLAYER_FIRST_PLACE => [
            "id" => 13,
            "name" => totranslate("Regions scored (First Place)"),
            "type" => "int"
        ],
        STAT_PLAYER_SECOND_PLACE => [
            "id" => 14,
            "name" => totranslate("Regions scored (Second Place)"),
            "type" => "int"
        ],
        STAT_PLAYER_LONE_WOLVES_CONVERTED => [
            "id" => 15,
            "name" => totranslate("Lone wolves converted"),
            "type" => "int"
        ],
        STAT_PLAYER_DENS_PLACED => [
            "id" => 16,
            "name" => totranslate("Dens placed"),
            "type" => "int"
        ],
        STAT_PLAYER_DENS_UPGRADED => [
            "id" => 17,
            "name" => totranslate("Dens upgraded to lairs"),
            "type" => "int"
        ],
        STAT_PLAYER_WOLVES_DOMINATED => [
            "id" => 18,
            "name" => totranslate("Wolves dominated"),
            "type" => "int"
        ],
        STAT_PLAYER_DENS_DOMINATED => [
            "id" => 19,
            "name" => totranslate("Dens dominated"),
            "type" => "int"
        ],
        STAT_PLAYER_WOLVES_MOVED => [
            "id" => 20,
            "name" => totranslate("Wolves moved"),
            "type" => "int"
        ],
        STAT_PLAYER_PREY_HUNTED => [
            "id" => 21,
            "name" => totranslate("Prey hunted"),
            "type" => "int"
        ],
        STAT_PLAYER_BONUS_ACTIONS_TAKEN => [
            "id" => 11,
            "name" => totranslate("Bonus actions taken"),
            "type" => "int"
        ],
        STAT_PLAYER_TERRAIN_TOKENS_SPENT => [
            "id" => 12,
            "name" => totranslate("Bonus terrain tokens spent"),
            "type" => "int"
        ]
    
/*
        Examples:    
        
        
        "player_teststat1" => array(   "id"=> 10,
                                "name" => totranslate("player test stat 1"), 
                                "type" => "int" ),
                                
        "player_teststat2" => array(   "id"=> 11,
                                "name" => totranslate("player test stat 2"), 
                                "type" => "float" )

*/    
    )

);
