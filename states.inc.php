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
 * states.inc.php
 *
 * Wolves game states description
 *
 */

/*
   Game state machine is a tool used to facilitate game developpement by doing common stuff that can be set up
   in a very easy way from this configuration file.

   Please check the BGA Studio presentation about game state to understand this, and associated documentation.

   Summary:

   States types:
   _ activeplayer: in this type of state, we expect some action from the active player.
   _ multipleactiveplayer: in this type of state, we expect some action from multiple players (the active players)
   _ game: this is an intermediary state where we don't expect any actions from players. Your game logic must decide what is the next game state.
   _ manager: special type for initial and final state

   Arguments of game states:
   _ name: the name of the GameState, in order you can recognize it on your own code.
   _ description: the description of the current game state is always displayed in the action status bar on
                  the top of the game. Most of the time this is useless for game state with "game" type.
   _ descriptionmyturn: the description of the current game state when it's your turn.
   _ type: defines the type of game states (activeplayer / multipleactiveplayer / game / manager)
   _ action: name of the method to call when this game state become the current game state. Usually, the
             action method is prefixed by "st" (ex: "stMyGameStateName").
   _ possibleactions: array that specify possible player actions on this step. It allows you to use "checkAction"
                      method on both client side (Javacript: this.checkAction) and server side (PHP: self::checkAction).
   _ transitions: the transitions are the possible paths to go from a game state to another. You must name
                  transitions in order to use transition names in "nextState" PHP method, and use IDs to
                  specify the next game state for each transition.
   _ args: name of the method to call to retrieve arguments for this gamestate. Arguments are sent to the
           client side to be used on "onEnteringState" or to set arguments in the gamestate description.
   _ updateGameProgression: when specified, the game progression is updated (=> call to your getGameProgression
                            method).
*/

//    !! It is not a good idea to modify this file when a game is running !!

$machinestates = [
    // The initial state. Please do not modify.
    ST_GAME_START => [
        'name' => 'gameSetup',
        'description' => '',
        'type' => 'manager',
        'action' => 'stGameSetup',
        'transitions' => ['' => ST_DRAFT_RESOLUTION]
    ],

    ST_DRAFT_RESOLUTION => [
        'name' => 'draftResolution',
        'description' => '',
        'type' => 'game',
        'action' => 'stDraftResolution',
        'updateGameProgression' => true,
        'transitions' => [TR_DRAFT_CONTINUE => ST_DRAFT_WOLVES, TR_DRAFT_END => ST_NEXT_TURN]
    ],

    ST_DRAFT_WOLVES => [
        'name' => 'draftWolves',
        'description' => clienttranslate('${actplayer} must place 🐺'),
        'descriptionmyturn' => clienttranslate('${you} must place 🐺'),
        'type' => 'activeplayer',
        'possibleactions' => ['draftPlace'],
        'transitions' => [TR_DRAFT_PLACE => ST_DRAFT_RESOLUTION]
    ],

    ST_NEXT_TURN => [
        'name' => 'nextTurn',
        'description' => clienttranslate('Starting next turn'),
        'type' => 'game',
        'action' => 'stNextTurn',
        'transitions' => [TR_START_TURN => ST_ACTION_SELECTION, TR_END_GAME => ST_GAME_END]
    ],

    ST_ACTION_SELECTION => [
        'name' => 'actionSelection',
        'description' => clienttranslate('${actplayer} must select a 🐺 action'),
        'descriptionmyturn' => clienttranslate('${you} must select a 🐺 action'),
        'type' => 'activeplayer',
        'possibleactions' => ['selectAction'],
        'action' => 'stPreActionSelection',
        'transitions' => [
            TR_HOWL_SELECT => ST_HOWL_SELECTION,
            TR_MOVE_SELECT => ST_MOVE_SELECTION,
            TR_DEN_SELECT => ST_DEN_SELECTION,
            TR_LAIR_SELECT => ST_LAIR_SELECTION,
            TR_DOMINATE_SELECT => ST_DOMINATE_SELECTION
        ]
    ],

    ST_HOWL_SELECTION => [
        'name' => 'howlSelection',
        'description' => clienttranslate('${actplayer} must select a howl target'),
        'descriptionmyturn' => clienttranslate('${you} must select a howl target'),
        'type' => 'activeplayer',
        'availableactions' => ['howl'],
        'transitions' => [TR_POST_ACTION => ST_POST_ACTION]
    ],

    ST_MOVE_SELECTION => [
        'name' => 'moveSelection',
        'description' => clienttranslate('${actplayer} must move ${numMoves} 🐺'),
        'descriptionmyturn' => clienttranslate('${you} must select a 🐺 to move'),
        'type' => 'activeplayer',
        'availableactions' => ['move'],
        'transitions' => [TR_MOVE => ST_MOVE_SELECTION, TR_DISPLACE => ST_DISPLACE, TR_END_MOVE => ST_POST_ACTION]
    ],

    ST_DISPLACE => [
        'name' => 'displaceWolf',
        'description' => clienttranslate('${actplayer} must displace ${displacedPlayer}\'s 🐺'),
        'descriptionmyturn' => clienttranslate('${you} must displace ${displacedPlayer}\'s 🐺'),
        'type' => 'activeplayer',
        'args' => 'argDisplaceSelection',
        'availableactions' => ['displace'],
        'transitions' => [TR_MOVE => ST_MOVE_SELECTION, TR_POST_ACTION => ST_POST_ACTION]
    ],

    ST_DEN_SELECTION => [
        'name' => 'denSelection',
        'description' => clienttranslate('${actplayer} must place a den'),
        'descriptionmyturn' => clienttranslate('${you} must select a den to place'),
        'type' => 'activeplayer',
        'availableactions' => ['den'],
        'transitions' => [TR_POST_ACTION => ST_POST_ACTION]
    ],

    ST_LAIR_SELECTION => [
        'name' => 'lairSelection',
        'description' => clienttranslate('${actplayer} must upgrade a den to a lair'),
        'descriptionmyturn' => clienttranslate('${you} must select a den to upgrade into a lair'),
        'type' => 'activeplayer',
        'availableactions' => ['lair'],
        'transitions' => [TR_POST_ACTION => ST_POST_ACTION, TR_DISPLACE => ST_DISPLACE]
    ],

    ST_DOMINATE_SELECTION => [
        'name' => 'dominateSelection',
        'description' => clienttranslate('${actplayer} must dominate one enemy piece'),
        'descriptionmyturn' => clienttranslate('${you} must select one enemy piece to dominate'),
        'type' => 'activeplayer',
        'availableactions' => ['dominate'],
        'transitions' => [TR_POST_ACTION => ST_POST_ACTION]
    ],

    ST_POST_ACTION => [
        'name' => 'postAction',
        'description' => clienttranslate('Updating...'),
        'descriptionmyturn' => clienttranslate('Updating...'),
        'type' => 'game',
        'action' => 'stPostAction',
        'updateGameProgression' => true,
        'transitions' => [TR_SELECT_ACTION => ST_ACTION_SELECTION, TR_CONFIRM_END => ST_CONFIRM_END] 
    ],

    ST_CONFIRM_END => [
        'name' => 'confirmEnd',
        'description' => clienttranslate('${actplayer} must end their turn'),
        'descriptionmyturn' => clienttranslate('${you} must spend bonus turn tiles, or end your turn'),
        'type' => 'activeplayer',
        'availableactions' => ['extraTurn', 'endTurn'],
        'transitions' => [TR_CONFIRM_END => ST_NEXT_TURN, TR_SELECT_ACTION => ST_ACTION_SELECTION]
    ],

    // Final state.
    // Please do not modify (and do not overload action/args methods).
    ST_GAME_END => [
        'name' => 'gameEnd',
        'description' => clienttranslate('End of game'),
        'type' => 'manager',
        'action' => 'stGameEnd',
        'args' => 'argGameEnd'
    ]
];



