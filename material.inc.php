<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * TheWolves implementation : © <Your name here> <Your email address here>
 * 
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 */

$this->terrainNames = [
    T_GRASS => 'grass',
    T_ROCK => 'rock',
    T_TUNDRA => 'tundra',
    T_DESERT => 'desert',
    T_FOREST => 'forest',
    T_WATER => 'water',
    T_CHASM => 'chasm'
];

$this->actionNames = [
    A_MOVE => clienttranslate('Move'),
    A_HOWL => clienttranslate('Howl'),
    A_DEN => clienttranslate('Build Den'),
    A_LAIR => clienttranslate('Upgrade Den'),
    A_DOMINATE => clienttranslate('Dominate')
];