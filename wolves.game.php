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
            'calendar' => self::CALENDAR_PROGRESS,
            G_SELECTED_TERRAIN => 10,
            G_ACTIONS_REMAINING => 11,
            G_MOVES_REMAINING => 12,
            G_MOVED_WOLVES => 13,
            G_DISPLACEMENT_WOLF => 14
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
        foreach ($players as $player_id => $player){
            $values[] = "('$player_id', '0', '0', '0', '0', '0')";
        }
        $args = implode(',', $values);
        $query = "INSERT INTO player_tiles (player_id, `0`, `1`, `2`, `3`, `4`) VALUES $args";
        self::DbQuery($query);

        $terrain = 0;
        $values = [];
        foreach ($players as $playerId => $player){
            $values[] = "($playerId, $terrain)";
            ++$terrain;
        }
        $args = implode(',', $values);
        $query = "INSERT INTO player_status (player_id, home_terrain) VALUES $args";
        self::DbQuery($query);

        $this->generateLand(count($players));
        $this->generatePieces($players);

        self::setGameStateInitialValue('calendar', 0);
        self::setGameStateInitialValue(G_SELECTED_TERRAIN, -1);
        self::setGameStateInitialValue(G_ACTIONS_REMAINING, -1);
        self::setGameStateInitialValue(G_MOVES_REMAINING, -1);
        self::setGameStateInitialValue(G_MOVED_WOLVES, -1);
        self::setGameStateInitialValue(G_DISPLACEMENT_WOLF, -1);

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
        $kind = P_LONE;

        foreach (BOARD_SETUP[count($players)] as $tile) {
            if (!array_key_exists('chasm', $tile)) {
                $center = $tile['center'];
                $scale = array_key_exists('rotate', $tile) ? -1 : 1;
                foreach (REGION_LONE_WOLVES as [$x, $y]) {
                    $x = $center[0] + $x * $scale;
                    $y = $center[1] + $y * $scale;
                    $values[] = "($x, $y, $kind)";
                }
            }
        }

        $args = implode(', ', $values);
        self::DbQuery("INSERT INTO pieces (x, y, kind) VALUES $args");
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

        $query = "SELECT id, owner, kind, x, y FROM pieces";
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
        $tiles = self::getObjectFromDB("SELECT `0`, `1`, `2`, `3`, `4` FROM `player_tiles` WHERE player_id=$player_id");
        $home_terrain = self::getUniqueValueFromDB("SELECT `home_terrain` FROM `player_status` WHERE player_id=$player_id");
        $tiles[] = $home_terrain;
        return $tiles;
    }

    function getPiecesInRange(int $x, int $y, int $range, int $terrain, $kinds, ?int $sourcePlayerId = null): array {
        [$xMin, $xMax] = [$x - $range, $x + $range];
        [$yMin, $yMax] = [$y - $range, $y + $range];
        $playerCheck = $sourcePlayerId === null ? '' : <<<EOF
                AND pieces.owner IS NOT NULL
                AND pieces.owner <> $sourcePlayerId
            GROUP BY x, y, owner
            HAVING COUNT(*) = 1
            ORDER BY NULL
            EOF;
        $kindCheck = is_int($kinds) ? "kind = $kinds" : 'kind IN (' . implode(', ', $kinds) . ')';

        $query = <<<EOF
            SELECT pieces.x, pieces.y FROM pieces NATURAL JOIN land
            WHERE land.x BETWEEN $xMin AND $xMax
                AND land.y BETWEEN $yMin AND $yMax
                AND terrain = $terrain
                AND $kindCheck
                AND (ABS(land.x - $x) + ABS(land.y - $y) + ABS(land.x - land.y - $x + $y)) / 2 <= $range
            $playerCheck
            EOF;
        return self::getObjectListFromDb($query);
    }

    function getValidConversionTargets(int $playerId, $kinds, bool $usePlayer): array {
        $kind = P_ALPHA;
        $alphas = self::getObjectListFromDB("SELECT x, y FROM pieces WHERE owner = $playerId AND kind = $kind");

        ['howl_range' => $range, 'selected_terrain' => $terrain]  =
            self::getObjectFromDB("SELECT howl_range, selected_terrain FROM player_status WHERE player_id = $playerId");
        $hexArrays = array_map(fn($wolf) =>
                $this->getPiecesInRange($wolf['x'], $wolf['y'], $range, $terrain, $kinds, $usePlayer ? $playerId : null),
            $alphas);
        return array_unique(array_merge(...$hexArrays));
    }

    function getValidHowlTargets(int $playerId): array {
        return $this->getValidConversionTargets($playerId, P_LONE, false);
    }

    function getValidDominateTargets(int $playerId): array {
        return $this->getValidConversionTargets($playerId, [P_PACK, P_DEN], true);
    }

    function getValidLandInRange(int $x, int $y, int $kind, int $player_id, int $range, int $terrain): array {
        [$xMin, $xMax] = [$x - $range, $x + $range];
        [$yMin, $yMax] = [$y - $range, $y + $range];
        $pack = P_PACK;
        $kinds = implode(", ", [P_ALPHA, P_LAIR, P_LONE, P_PREY]);
        $query = <<<EOF
            SELECT l.*
            FROM land l NATURAL LEFT JOIN pieces p
            WHERE l.x BETWEEN $xMin AND $xMax
                AND l.y BETWEEN $yMin AND $yMax
                AND l.terrain = $terrain
                AND (ABS(l.x - $x) + ABS(l.y - $y) + ABS(l.x - l.y - $x + $y)) / 2 <= $range
                AND (p.id IS NULL OR (SELECT COUNT(*) FROM pieces WHERE x = l.x AND y = l.y) < 2)
                AND (p.kind IS NULL OR $kind != $pack OR p.kind != $pack OR p.owner = $player_id)
                AND (p.kind IS NULL OR p.kind NOT IN ($kinds) OR p.owner = $player_id)
            EOF;
        return self::getObjectListFromDB($query);
    }

    function getValidMoves(int $x, int $y, int $kind, int $player_id, int $terrain, int $range, int $piece_id): array {

        if(in_array($piece_id, array_map(fn($wolf) => $wolf['id'], $this->getMovedWolves()))){
            return [];
        }
        [$xMin, $xMax] = [$x - $range, $x + $range];
        [$yMin, $yMax] = [$y - $range, $y + $range];
        //print("Processing wolf at ($x, $y). xMax=$xMax, xMin=$xMin, yMax=$yMax, yMin=$yMin");
        // Create a queue to store the tiles to visit
        $queue = new SplQueue();

        // Initialize a visited array to keep track of visited tiles
        $visited = [];

        $valid = [];
        
        // Add the source tile to the queue
        $queue->enqueue([$x, $y, 0]);

        $objects = self::getObjectListFromDB("SELECT x,y,terrain FROM land WHERE x >= $xMin AND x <= $xMax
        AND y >= $yMin AND y <= $yMax");

        $validLands = $this->getValidLandInRange($x, $y, $kind, $player_id, $range, $terrain);

        $terrainArray = [];
        foreach ($objects as $object) {
            $x = $object["x"];
            $y = $object["y"];
            $terrain = $object["terrain"];

            if (!isset($terrainArray[$x])) {
                $terrainArray[$x] = [];
            }

            $terrainArray[$x][$y] = $terrain;
        }

        $validLandArray = [];
        foreach($validLands as $validLand){
            $x = $validLand["x"];
            $y = $validLand["y"];

            if(!isset($validLandArray[$x])){
                $validlandArray[$x] = [];
            }

            $validLandArray[$x][$y] = true;
        }
        while(!$queue->isEmpty()){
            [$currentX, $currentY, $moves] = $queue->dequeue();
            if(!isset($visited[$currentX])){
                $visited[$currentX] = [];
            }
            
            $validTerrain = $terrainArray[$currentX][$currentY] != T_WATER;
            $hasBeenVisited = !isset($visited[$currentX][$currentY]);
            //print("Processing ($currentX, $currentY), moves=$moves, validTerrain=$validTerrain, hasBeenVisited=$hasBeenVisited");
            if ($moves <= $range && $validTerrain && $hasBeenVisited) {


                $withinRange = $moves > 0;
                $validX = isset($validLandArray[$currentX]);
                $validY = isset($validLandArray[$currentX][$currentY]);
                //print("withinRange = $withinRange, validX = $validX, validY = $validY");
                if($withinRange && $validX && $validY){
                    //print("($currentX, $currentY) is valid");
                    $valid[] = [$currentX, $currentY];
                }

                // Enqueue neighboring tiles for exploration
                foreach (HEX_DIRECTIONS as [$dx, $dy]) {
                    if(isset($terrainArray[$currentX + $dx]) && isset($terrainArray[$currentX + $dx][$currentY + $dy])){
                        $queue->enqueue([$currentX + $dx, $currentY + $dy, $moves + 1]);
                    }
                    
                }
            }
            // Mark the current tile as visited
            $visited[$currentX][$currentY] = true;
        }
        //print_r($valid);
        return $valid;
    }

    function getValidMoveTargets(int $playerId): array {
        $pieces = self::getObjectListFromDB("SELECT id, kind, x, y FROM pieces WHERE owner=$playerId");
        $wolves = array_filter($pieces, fn($piece) => $piece['kind'] == P_ALPHA || $piece['kind'] == P_LONE);
        $max_moves = 2; //TODO: Update with actual player value
        return array_map(fn($wolf) => [$wolf['id'] => $this->getValidMoves($wolf['x'], $wolf['y'], $wolf['kind'], $playerId, $this->getGameStateValue(G_SELECTED_TERRAIN), $max_moves, $wolf['id'])], $wolves);
    }

    function checkPath(int $playerId, array $start, array $moves, int $kind, int $terrain): bool {
        $checks = [];
        foreach (array_map(fn($move) => HEX_DIRECTIONS[$move], $moves) as [$dx, $dy]) {
            $start[0] += $dx;
            $start[1] += $dy;
            $checks[] = "x = $start[0] AND y = $start[1]";
        }

        $args = implode(" OR ", $checks);
        $isPathValid = self::getUniqueValueFromDB("SELECT COUNT(*) FROM land WHERE $args") === count($moves);

        if ($isPathValid) {
            if ($kind === P_ALPHA) {
                $kinds = implode(", ", [P_PACK, P_DEN]);
                $alphaConstraint = "owner <> $playerId AND kind IN ($kinds) AND COUNT(*) = 1";
            } else {
                $alphaConstraint = 'FALSE';
            }

            $query = <<<EOF
                SELECT COUNT(*) FROM land NATURAL LEFT JOIN pieces
                WHERE x = $start[0] AND y = $start[1]
                    AND terrain = $terrain
                GROUP BY owner
                HAVING pieces.id == NULL
                    OR owner = $playerId AND COUNT(*) = 1
                    OR $alphaConstraint
                ORDER BY NULL
                EOF;
            return self::getUniqueValueFromDB($query) !== null;
        }

        return false;
    }

    function checkDisplacement(int $playerId, array $start, array $moves) {
        if (count($moves) === 1) {
            [$dx, $dy] = HEX_DIRECTIONS[$moves[0]];
            $start[0] += $dx;
            $start[1] += $dy;
            $query = <<<EOF
                SELECT COUNT(*) FROM land NATURAL LEFT JOIN pieces
                WHERE x = $start[0] AND y = $start[1]
                GROUP BY owner
                HAVING pieces.id == NULL
                    OR owner = $playerId AND COUNT(*) = 1
                ORDER BY NULL
                EOF;
            return self::getUniqueValueFromDB($query) !== null;
        } else {
            //TODO do the search
            return false;
        }
    }


    function addMovedWolf(int $wolfId){
        $moved_wolves = $this->getGameStateValue(G_MOVED_WOLVES);
        $moved_wolves = $moved_wolves << 8;
        $moved_wolves |= ($wolfId & 0xff);
        $this->setGameStateValue(G_MOVED_WOLVES, $moved_wolves);
    }

    function getMovedWolves(): array {
        $moved_wolves = $this->getGameStateValue(G_MOVED_WOLVES);
        $wolf_ids = [];
        for($i = 0; $i<4; $i++){
            $wolf_id = ($moved_wolves >> (8 * $i)) & 0xff;
            if($wolf_id === 0xff){
                continue;
            }
            $wolf_ids[] = $wolf_id;
        }

        return array_map(fn($id) => self::getObjectFromDB("SELECT * FROM pieces WHERE id=$id"), $wolf_ids);

    }

    function flipTiles(int $playerId, array $tileIndices): int {
        $tiles = $this->getPlayerTiles($playerId);
        $terrain = -1;
        $sets = [];
        print_r($tileIndices);

        foreach ($tileIndices as $tileIndex) {
            $nextTerrain = $tileIndex < TILE_TERRAIN_TYPES ?
                ($tileIndex + $tiles[strval($tileIndex)]) % TILE_TERRAIN_TYPES :
                self::getUniqueValueFromDB("SELECT home_terrain FROM player_status WHERE player_id = $playerId");

            if ($terrain >= 0 && $nextTerrain !== $terrain) {
                throw new BgaUserException(_('All tiles must have identical terrain'));
            }
            $terrain = $nextTerrain;

            if ($tileIndex < TILE_TERRAIN_TYPES) {
                $sets[] = "`$tileIndex` = 1 - `$tileIndex`";
            }
        }

        $update = implode(", ", $sets);
        $query = "UPDATE player_tiles SET $update WHERE player_id = $playerId";
        self::DbQuery($query);

        return $terrain;
    }

    function getPossibleHexes(int $playerId, int $action, int $terrain): array {
        //TODO: Implement this method
    }

    function canDisplaceWolf(int $x, int $y, $playerId): boolean {
        $query = <<<EOF
                    SELECT land.terrain as terrain, COUNT(SELECT * FROM pieces WHERE x=$x AND y=$y) as pieces_count, GROUP_CONCAT(pieces.kind) as kinds, GROUP_CONCAT(pieces.owner) as owners
                    FROM land
                    LEFT JOIN pieces ON pieces.x = land.x AND pieces.y = land.y
                    GROUP BY land.x, land.y
                    HAVING land.x=$x AND land.y=$y
                    EOF;
        $validityCheck = self::getObjectFromDB($query);
        return $validityCheck['terrain'] != T_WATER 
        && $validityCheck['pieces_count'] < 2 
        && ($validityCheck['pieces_count'] == 0 
            || $validityCheck['kinds'][0] == P_DEN 
            || $validityCheck['owners'][0] == $playerId);
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

        $values = array_map(fn($piece) => "($x, $y, $player_id, $piece)", [P_ALPHA, P_PACK]);
        $args = implode(", ", $values);
        $query = "INSERT INTO pieces (x, y, owner, kind) VALUES $args";
        self::DbQuery($query);

        $this->notifyAllPlayers('draft', clienttranslate('${player_name} placed initial 🐺.'), [
            'player_name' => self::getActivePlayerName(),
            'player_id' => $player_id,
            'x' => $x,
            'y' => $y
        ]);

        $this->gamestate->nextState('draftPlace');
    }

    function selectAction(int $action, array $tiles, int $bonusTerrain, ?int $forceTerrain = null): void {
        self::checkAction('selectAction');
        if (!array_key_exists($action, ACTION_COSTS)) {
            throw new BgaUserException(_('Invalid action selected'));
        }

        $playerId = self::getActivePlayerId();

        if ($bonusTerrain > self::getUniqueValueFromDb("SELECT terrain_tokens FROM player_status WHERE player_id = $playerId")) {
            throw new BgaUserException(_('Not enough bonus terrain tokens'));
        }

        $cost = ACTION_COSTS[$action];
        if ($forceTerrain === null && count($tiles) + $bonusTerrain != $cost) {
            throw new BgaUserException(_('${count} tile(s) need to be flipped for this action'));
        }

        $terrain = $forceTerrain ?? $this->flipTiles(self::getActivePlayerId(), $tiles);

        $this->notifyAllPlayers('action', clienttranslate('${player_name} chooses to ${action_name} at ${terrain_name}.'), [
            'player_name' => self::getActivePlayerName(),
            'player_id' => $playerId,
            'action_name' => $this->actionNames[$action],
            'terrain_name' => $this->terrainNames[$terrain],
            'new_tiles' => $this->getPlayerTiles($playerId)
        ]);

        if($bonusTerrain){
            $query = "UPDATE player_status SET terrain_tokens = terrain_tokens - $bonusTerrain WHERE player_id = $playerId";
            self::DbQuery($query);
        }


        $this->setGameStateValue(G_SELECTED_TERRAIN, $terrain);

        $actionName = $this->actionNames[$action]
        switch($actionName){
            case 'move':
                $this->setGameStateValue(G_MOVED_WOLVES, -1);
                break;
            default:
                break;
        }

        $this->gamestate->nextState( $actionName . "Select");
    }

    function testMove(): void {
        $this->selectAction(A_MOVE, [2], 0);
    }

    function testHowl(): void {
        $this->selectAction(A_HOWL, [], 0, T_TUNDRA);
    }

    function move(int $wolfId, int $kind, array $path): void {
        self::checkAction('move');

        $playerId = self::getActivePlayerId();
        $terrain_type = $this->getGameStateValue(G_SELECTED_TERRAIN);
        $query = "SELECT * FROM pieces WHERE id=$wolfId";
        $wolf = self::getObjectFromDB($query);
        $isAlpha = $wolf['kind'] == P_ALPHA;
        if($wolf == NULL || $wolf['owner'] != $playerId || $wolf['kind'] > P_PACK){
            throw new BgaUserException(_('The wolf you selected is not valid!'));
        }

        //Verify move is valid

        $max_distance = 2 //TODO: Update with actual value
        if(count($path) > $max_distance){
            throw new BgaUserException(_('The selected tile is out of range'));
        }

        $x = $wolf['x'];
        $y = $wolf['y'];
        foreach($path as $index){
            $move = HEX_DIRECTIONS[$index];
            $x += $move[0];
            $y += $move[1];

            $hex = self::getObjectFromDB("SELECT * FROM land WHERE x=$x AND y=$y");
            if($hex == NULL || $hex['terrain'] == T_WATER){
                throw new BgaUserException(_('Cannot find a clear path to the given tile'));
            } 
        }

        $finalPieces = self::getObjectListFromDB("SELECT * FROM pieces WHERE x=$x AND y=$y");
        switch(count($finalPieces)){
            case 1:
                $piece = $finalPieces[0];
                if(!($piece['owner'] == $playerId || ($isAlpha && $piece['kind'] == P_PACK) || $piece['kind'] == P_DEN)){
                    throw new BgaUserException(_('Invalid move location'));
                }
                break;
            case 2:
                throw new BgaUserException(_('Invalid move location'));
            default:
                break;
        }

        $query = "SELECT * FROM pieces WHERE x=$targetX AND y=$targetY AND kind=1 AND owner != $playerId";
        $potential_wolves = self::getObjectListFromDB($query);

        $query = "UPDATE pieces SET x=$targetX, y=$targetY WHERE id=$wolfId";
        self::DbQuery($query);

        $this->addMovedWolf($wolf['id']);
        $this->incGameStateValue(G_MOVES_REMAINING, -1);
        if(count($potential_wolves) > 0){
            $pack_wolf = $potential_wolves[0];
            $this->setGameStateValue(G_DISPLACEMENT_WOLF, $pack_wolf['id']);
            $this->gamestate->nextState(T_DISPLACE);
        }
        else{
            $this->gamestate->nextState(($this->getGameStateValue(G_MOVES_REMAINING) > 0) ? T_MOVE : T_END_MOVE);
        }  
    }

    function displace(int $x, int $y): void {

        self::checkAction('displace');
        $playerId = self::getActivePlayerId();
        $wolfId = $this->getGameStateValue(G_DISPLACEMENT_WOLF);
        $wolf = self::getObjectFromDB("SELECT * FROM pieces WHERE id=$wolfId");
        $range = 1 //TODO: Implement logic to not get soft locked
        //AND (ABS(land.x - $x) + ABS(land.y - $y) + ABS(land.x - land.y - $x + $y)) / 2 <= $range
        if(!canPlaceWolf($x, $y, $playerId) && (abs($x - $wolf['x']) + abs($y - $wolf['y']) + abs($wolf['x'] - $wolf['y'] - $x +$y)) / 2.0 > $range){
            throw new BgaUserException(_('Invalid Move Location'));
        }

    }

    function howl(int $x, int $y): void {
        self::checkAction('howl');
        $playerId = self::getActivePlayerId();
        $targets = $this->getValidHowlTargets($playerId);
        if (!in_array(['x' => $x, 'y' => $y], $targets)) {
            throw new BgaUserException(_('Invalid howl target'));
        }

        $this->activeNextPlayer();
        $this->gamestate->nextState(T_HOWL);
    }

    function placeDen(int $attribute, int $x, int $y): void {
        self::checkAction('placeDen');
    }

    function placeLair(int $attribute, int $x, int $y): void {
        self::checkAction('placeLair');
    }

    function dominate(int $piece_id): void {
        self::checkAction('dominate');
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

    function argHowlSelection(): array {
        return [
            'validTargets' => $this->getValidHowlTargets(self::getActivePlayerId())
        ];
    }

    function argMoveSelection(): array {
        return [
            'validTargets' => $this->getValidMoveTargets(self::getActivePlayerId())
        ];
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.    */

    function stDraftResolution(): void {

        $wolvesDrafted = self::getUniqueValueFromDB(
            "SELECT COUNT(*) FROM pieces WHERE owner IS NOT NULL");
        $draftCompleted = $wolvesDrafted >= 2 * self::getPlayersNumber();

        if ($wolvesDrafted > 0 && !$draftCompleted) {
            $this->activeNextPlayer();
        }

        $this->gamestate->nextState($draftCompleted ? T_DRAFT_END : T_DRAFT_CONTINUE);
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
