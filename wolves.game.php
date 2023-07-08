<?php
 /**
  *------
  * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
  * Wolves implementation : © <Your name here> <Your email address here>
  * 
  * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
  * See http://en.boardgamearena.com/#!doc/Studio for more information.
  * -----
  */

require_once(APP_GAMEMODULE_PATH.'module/table/table.game.php');
require_once('modules/constants.inc.php');

class Wolves extends Table {
    const CALENDAR_PROGRESS = 20;

    function __construct() {
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();
        
        self::initGameStateLabels([
            'calendar' => self::CALENDAR_PROGRESS
        ]);
    }

    protected function getGameName(): string {
        // Used for translations and stuff. Please do not modify.
        return 'wolves';
    }	

    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame($players, $options = []): void {
        $gameinfos = self::getGameinfos();
        $default_colors = $gameinfos['player_colors'];

        // Create players
        $values = [];
        foreach ($players as $player_id => $player) {
            $color = array_shift($default_colors);
            $name = addslashes($player['player_name']);
            $avatar = addslashes($player['player_avatar']);
            $values[] = "('$player_id','$color','$player[player_canal]','$name','$avatar')";
        }
        $args = implode(',', $values);
        $query = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES $args";
        self::DbQuery($query);

        self::reloadPlayersBasicInfos();
        
        /************ Start the game initialization *****/

        // Init global values with their initial values
        //self::setGameStateInitialValue( 'my_first_global_variable', 0 );
        
        // Init game statistics
        // (note: statistics used in this file must be defined in your stats.inc.php file)
        //self::initStat( 'table', 'table_teststat1', 0 );    // Init a table statistics
        //self::initStat( 'player', 'player_teststat1', 0 );  // Init a player statistics (for all players)

        //Initialize player tiles
        $values = [];
        foreach($players as $player_id => $player){
            $values[] = "('$player_id', '0', '0', '0', '0', '0')";
        }
        $args = implode(',', $values);
        $query = "INSERT INTO player_tiles (player_id, `0`, `1`, `2`, `3`, `4`) VALUES $args";
        self::DbQuery($query);

        $this->generateLand(count($players));
        $this->generatePieces($players);

        self::setGameStateInitialValue('calendar', 0);

        // Activate first player (which is in general a good idea :) )
        $this->activeNextPlayer();

        /************ End of the game initialization *****/
    }

    protected function generateLand(int $player_count): void {
        $values = [];
        $region_hexes = array_map(null, HEX_COORDS, REGION_HEXES);
        $region_palettes = REGION_PALETTES;
        shuffle($region_palettes);

        foreach (BOARD_SETUP[$player_count] as $tile) {
            $center = $tile['center'];
            [$palette, $hexes] = array_key_exists('chasm', $tile) ?
                [CHASM_PALLETTE, array_map(null, HEX_COORDS, CHASM_HEXES)] :
                [array_shift($region_palettes), $region_hexes];
            $scale = array_key_exists('rotate', $tile) ? -1 : 1;
            foreach ($hexes as [$coord, $palette_index]) {
                $x = $center[0] + $coord[0] * $scale;
                $y = $center[1] + $coord[1] * $scale;
                $type = $palette[$palette_index];
                $values[] = "($x, $y, $type)";
            }
        }

        $args = implode(', ', $values);
        self::DbQuery("INSERT INTO land VALUES $args");
    }

    protected function generatePieces(array $players): void {
        $values = [];
        $wolves = [[P_ALPHA, L_PLAYER], [P_PACK, L_PLAYER]];

        foreach ($players as $player_id => $player) {
            foreach ($wolves as [$kind, $location]) {
                $values[] = "($player_id, $kind, $location)";
            }
        }

        $args = implode(', ', $values);
        self::DbQuery("INSERT INTO pieces (owner, kind, location) VALUES $args");

        $values = [];
        $kind = P_LONE;
        $location = L_BOARD;

        foreach (BOARD_SETUP[count($players)] as $tile) {
            if (!array_key_exists('chasm', $tile)) {
                $center = $tile['center'];
                $scale = array_key_exists('rotate', $tile) ? -1 : 1;
                foreach (REGION_LONE_WOLVES as [$x, $y]) {
                    $x = $center[0] + $x * $scale;
                    $y = $center[1] + $y * $scale;
                    $values[] = "($x, $y, $kind, $location)";
                }
            }
        }

        $args = implode(', ', $values);
        self::DbQuery("INSERT INTO pieces (x, y, kind, location) VALUES $args");
    }

    /*
        getAllDatas: 
        
        Gather all informations about current game situation (visible by the current player).
        
        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)
    */
    protected function getAllDatas(): array {
        $result = [];

        // Get information about players
        $query = 'SELECT player_id id, player_score score, player_color color FROM player';
        $result['players'] = self::getCollectionFromDb($query);

        $location = L_BOARD;
        $query = "SELECT id, owner, x, y FROM pieces WHERE location = $location";
        $result['pieces'] = self::getCollectionFromDb($query);

        return $result;
    }

    /*
        getGameProgression:
        
        Compute and return the current game progression.
        The number returned must be an integer beween 0 (=the game just started) and
        100 (= the game is finished or almost finished).
    
        This method is called each time we are in a game state with the "updateGameProgression" property set to true 
        (see states.inc.php)
    */
    function getGameProgression(): int {
        // TODO: compute and return the game progression

        return 0;
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////    

    function getLand(): array {
        return self::getObjectListFromDB('SELECT * FROM land');
    }

    function getPlayerTiles(int $player_id): array {
        return self::getObjectFromDB("SELECT `0`, `1`, `2`, `3`, `4` FROM `player_tiles` WHERE player_id=$player_id");
    }

    function getPiecesInRange(int $x, int $y, int $range, int $terrain, int $kind): array {
        [$xMin, $xMax] = [$x - $range, $x + range];
        [$yMin, $yMax] = [$y - $range, $y + range];
        $query = <<<EOF
            SELECT x, y FROM pieces
            JOIN land ON pieces.x = land.x AND pieces.y = land.y
            WHERE x >= $xMin AND x <= $xMax
                AND y >= $yMin AND y <= $yMax
                AND terrain = $terrain
                AND kind = $kind
                AND (ABS(x - $x) + ABS(y - $y) + ABS(x + y - $x - $y)) / 2 <= $range
            EOF;
        return self::getCollectionFromDb($query);
    }

    function flipTiles(int $playerId, array $tile_indices): int {
        $tiles = $this->getPlayerTiles($playerId);
        $terrain = -1;
        $sets = [];

        foreach ($tile_indices as $tile_index) {
            $nextTerrain = ($tile_index + $tiles[strval($tile_index)]) % TILE_TERRAIN_TYPES;
            if ($terrain >= 0 && $nextTerrain !== $terrain) {
                throw new BgaUserException(_('All tiles must have identical terrain'));
            }
            $sets[] = "`$tile_index` = 1 - `$tile_index`";
            $terrain = $nextTerrain;
        }

        $update = implode(", ", $sets);
        $query = "UPDATE player_tiles SET $update WHERE player_id = $playerId";
        self::DbQuery($query);

        return $terrain;
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Player actions
//////////// 

    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in wolves.action.php)
    */

    function draftPlace(int $x, int $y): void {
        self::checkAction('draftPlace');
        $player_id = self::getActivePlayerId();
        $query = "SELECT COUNT(*) FROM pieces WHERE x = $x AND y = $y";
        if (self::getUniqueValueFromDB($query) > 0 ) {
            throw new BgaUserException(_("This place is already occupied"));
        }

        $location = L_BOARD;
        $query = "UPDATE pieces SET location = $location, x = $x, y = $y WHERE owner = $player_id";
        self::DbQuery($query);

        $this->notifyAllPlayers('draft', clienttranslate('${player_name} placed initial 🐺.'), [
            'player_name' => self::getActivePlayerName(),
            'player_id' => $player_id,
            'x' => $x,
            'y' => $y
        ]);

        $this->gamestate->nextState('draftPlace');
    }

    function selectAction(int $action, array $tiles): void {
        self::checkAction('selectAction');
        if (!array_key_exists($action, ACTION_COSTS)) {
            throw new BgaUserException(_('Invalid action selected'));
        }

        $cost = ACTION_COSTS[$action];
        if (count($tiles) != $cost) {
            throw new BgaUserException(_('${count} tile(s) need to be flipped for this action'));
        }

        $terrain = $this->flipTiles(self::getActivePlayerId(), $tiles);
        $playerId = self::getActivePlayerId();

        $this->notifyAllPlayers('action', clienttranslate('${player_name} chooses to ${action_name} at ${terrain_name}.'), [
            'player_name' => self::getActivePlayerName(),
            'player_id' => $playerId,
            'action_name' => $this->actionNames[$action],
            'terrain_name' => $this->terrainNames[$terrain],
            'new_tiles' => $this->getPlayerTiles($playerId)
        ]);

        $this->gamestate->nextState('selectAction');
    }

    function testSelectAction(): void {
        $this->selectAction(A_MOVE, [2]);
    }

    function moveWolf(int $wolf_id, int $x, int $y): void {
        self::checkAction('moveWolf');
    }

    function placeDen(int $attribute, int $x, int $y): void {
        self::checkAction('placeDen');
    }

    function placeLair(int $attribute, int $x, int $y): void {

    }

    function dominate(int $attribute, int $x, int $y): void {

    }

    /*
    
    Example:

    function playCard( $card_id )
    {
        // Check that this is the player's turn and that it is a "possible action" at this game state (see states.inc.php)
        self::checkAction( 'playCard' ); 
        
        $player_id = self::getActivePlayerId();
        
        // Add your game logic to play a card there 
        ...
        
        // Notify all players about the card played
        self::notifyAllPlayers( "cardPlayed", clienttranslate( '${player_name} plays ${card_name}' ), array(
            'player_id' => $player_id,
            'player_name' => self::getActivePlayerName(),
            'card_name' => $card_name,
            'card_id' => $card_id
        ) );
          
    }
    
    */

    
//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */

    /*
    
    Example for game state "MyGameState":
    
    function argMyGameState()
    {
        // Get some values from the current game situation in database...
    
        // return values:
        return array(
            'variable1' => $value1,
            'variable2' => $value2,
            ...
        );
    }    
    */

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.    */

    function stDraftResolution(): void {
        $location = L_BOARD;

        $wolvesDrafted = self::getUniqueValueFromDB(
            "SELECT COUNT(*) FROM pieces WHERE location = $location AND owner IS NOT NULL");
        $draftCompleted = $wolvesDrafted >= 2 * self::getPlayersNumber();

        if ($wolvesDrafted > 0 && !$draftCompleted) {
            $this->activeNextPlayer();
        }

        $this->gamestate->nextState($draftCompleted ? 'draftEnd' : 'draftContinue');
    }

    function stDispatchAction(): void {
        $this->activeNextPlayer();
        $this->gamestate->nextState('');
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Zombie
////////////

    /*
        zombieTurn:
        
        This method is called each time it is the turn of a player who has quit the game (= "zombie" player).
        You can do whatever you want in order to make sure the turn of this player ends appropriately
        (ex: pass).
        
        Important: your zombie code will be called when the player leaves the game. This action is triggered
        from the main site and propagated to the gameserver from a server, not from a browser.
        As a consequence, there is no current player associated to this action. In your zombieTurn function,
        you must _never_ use getCurrentPlayerId() or getCurrentPlayerName(), otherwise it will fail with a "Not logged" error message. 
    */

    function zombieTurn($state, $active_player): void {
        $statename = $state['name'];

        if ($state['type'] === "activeplayer") {
            switch ($statename) {
                default:
                    $this->gamestate->nextState( "zombiePass" );
                    break;
            }

            return;
        }

        if ($state['type'] === "multipleactiveplayer") {
            // Make sure player is in a non blocking status for role turn
            $this->gamestate->setPlayerNonMultiactive( $active_player, '' );
            
            return;
        }

        throw new feException( "Zombie mode not supported at this game state: ".$statename );
    }
    
///////////////////////////////////////////////////////////////////////////////////:
////////// DB upgrade
//////////

    /*
        upgradeTableDb:
        
        You don't have to care about this until your game has been published on BGA.
        Once your game is on BGA, this method is called everytime the system detects a game running with your old
        Database scheme.
        In this case, if you change your Database scheme, you just have to apply the needed changes in order to
        update the game database and allow the game to continue to run with your new version.
    
    */
    
    function upgradeTableDb($from_version): void {
        // $from_version is the current version of this game database, in numerical form.
        // For example, if the game was running with a release of your game named "140430-1345",
        // $from_version is equal to 1404301345
        
        // Example:
//        if( $from_version <= 1404301345 )
//        {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "ALTER TABLE DBPREFIX_xxxxxxx ....";
//            self::applyDbUpgradeToAllDB( $sql );
//        }
//        if( $from_version <= 1405061421 )
//        {
//            // ! important ! Use DBPREFIX_<table_name> for all tables
//
//            $sql = "CREATE TABLE DBPREFIX_xxxxxxx ....";
//            self::applyDbUpgradeToAllDB( $sql );
//        }
//        // Please add your future database scheme changes here
//
//
    }    
}
