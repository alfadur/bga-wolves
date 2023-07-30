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
            G_DISPLACEMENT_WOLF => 14,
            G_MOON_PHASE => 16,
            G_FLIPPED_TILES => 17,
            G_SPENT_TERRAIN_TOKENS => 18
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
        self::setGameStateInitialValue(G_MOVED_WOLVES, 0);
        self::setGameStateInitialValue(G_DISPLACEMENT_WOLF, -1);
        self::setGameStateInitialValue(G_MOON_PHASE, 0);
        self::setGameStateInitialValue(G_FLIPPED_TILES, 0);
        self::setGameStateInitialValue(G_SPENT_TERRAIN_TOKENS, 0);

        // Activate first player (which is in general a good idea :) )
        $this->activeNextPlayer();

        /************ End of the game initialization *****/
    }

    protected function generateLand(int $player_count): void {
        $region_values = [];
        $land_values = [];
        $region_hexes = array_map(null, HEX_COORDS, REGION_HEXES);
        $region_palettes = array_map(null, range(1, count(REGION_PALETTES)), REGION_PALETTES);
        shuffle($region_palettes);

        $moonPhases = START_MOON_PHASES[$player_count];

        $phase_num = 0;
        $region_id = 1;
        foreach (BOARD_SETUP[$player_count] as $tile) {
            $center = $tile['center'];
            [[$tile_index, $palette], $hexes] = array_key_exists('chasm', $tile) ?
                [[0, CHASM_PALLETTE], array_map(null, HEX_COORDS, CHASM_HEXES)] :
                [array_shift($region_palettes), $region_hexes];
            $rotate = (int)array_key_exists('rotate', $tile);
            $scale = $rotate ? -1 : 1;
            $phase = array_key_exists('chasm', $tile) ? M_NONE : $moonPhases[$phase_num++];
            $region_values[] = "($tile_index, $center[0], $center[1], $rotate, $phase)";
            foreach ($hexes as [$coord, $palette_index]) {
                $x = $center[0] + $coord[0] * $scale;
                $y = $center[1] + $coord[1] * $scale;
                $type = $palette[$palette_index];
                
                $land_values[] = "($x, $y, $type, $region_id)";
            }
            ++$region_id;
        }

        $args = implode(', ', $region_values);
        self::DbQuery("INSERT INTO regions (tile_number, center_x, center_y, rotated, moon_phase) VALUES $args");
        $args = implode(', ', $land_values);
        self::DbQuery("INSERT INTO land VALUES $args");
    }

    protected function generatePieces(array $players): void {
        $values = [];
        $kind = P_LONE;
        $prey_kind = P_PREY;
        $available_prey = AVAILABLE_PREY[count($players)];
        shuffle($available_prey);

        foreach (BOARD_SETUP[count($players)] as $tile) {
            if (!array_key_exists('chasm', $tile)) {
                
                $center = $tile['center'];
                $scale = array_key_exists('rotate', $tile) ? -1 : 1;
                foreach (REGION_LONE_WOLVES as [$x, $y]) {
                    $x = $center[0] + $x * $scale;
                    $y = $center[1] + $y * $scale;
                    $values[] = "($x, $y, $kind, NULL)";
                }
                [$preyType => $numPrey] = array_pop($available_prey);

                [$preyX, $preyY] = REGION_PREY;
                $x = $center[0] + $preyX * $scale;
                $y = $center[1] + $preyY * $scale;
                for($i = 0; $i<$numPrey; $i++){
                    $values[] = "($x, $y, $prey_kind, $preyType)";
                }
            }
        }

        $args = implode(', ', $values);
        self::DbQuery("INSERT INTO pieces (x, y, kind, prey_metadata) VALUES $args");
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
        $result['players'] = self::getCollectionFromDb(
            'SELECT player_id id, player_score score, player_color color FROM player');
        $result['status'] = self::getObjectListFromDb('SELECT * FROM player_status');
        $result['regions'] = self::getObjectListFromDb(
            'SELECT tile_number, center_x, center_y, rotated FROM regions');
        $result['pieces'] = self::getObjectListFromDb("SELECT id, owner, kind, x, y FROM pieces");
        $result['calendar'] = self::getObjectListFromDb("SELECT player_id AS owner, kind FROM moonlight_board");

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

        $numEntries = self::getUniqueValueFromDB("SELECT COUNT(*) FROM moonlight_board");
        return intval(($numEntries * 100) / FULL_DATES[self::getPlayersNumber()]);
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////    

    function getRegions(): array {
        return self::getObjectListFromDB('SELECT * FROM regions');
    }

    function getMoonPhases(int $regionId): array {
        $phase = self::getUniqueValueFromDB("SELECT moon_phase from regions WHERE region_id=$regionId");
        $phases = [];
        if($phase == NULL){
            return $phases;
        }
        for($i = 0b1; $i<0b1000; $i *= 2){
            if($phase & $i > 0){
                $phases[] = $i;
            }
        }
        return $phases;
    }

    static function sql_hex_range($x1, $y1, $x2, $y2) {
        return "(ABS($x2 - $x1) + ABS($y2 - $y1) + ABS($x2 - $y2 - $x1 + $y1)) / 2";
    }

    static function sql_hex_in_range($x1, $y1, $x2, $y2, $range) {
        return "ABS($x2 - $x1) + ABS($y2 - $y1) + ABS($x2 - $y2 - $x1 + $y1) <= 2 * $range";
    }

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
            SELECT x, y FROM pieces NATURAL JOIN land
            WHERE x BETWEEN $xMin AND $xMax
                AND y BETWEEN $yMin AND $yMax
                AND terrain = $terrain
                AND $kindCheck
                AND {$this->sql_hex_in_range('x', 'y', $x, $y, $range)}
            $playerCheck
            EOF;
        return self::getObjectListFromDb($query);
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
                AND {$this->sql_hex_in_range('l.x', 'l.y', $x, $y, $range)}
                AND (p.id IS NULL OR (SELECT COUNT(*) FROM pieces WHERE x = l.x AND y = l.y) < 2)
                AND (p.kind IS NULL OR $kind != $pack OR p.kind != $pack OR p.owner = $player_id)
                AND (p.kind IS NULL OR p.kind NOT IN ($kinds) OR p.owner = $player_id)
            EOF;
        return self::getObjectListFromDB($query);
    }

    function validityCheck($x, $y){
        $hex = self::getObjectFromDB("SELECT * FROM land WHERE x=$x AND y=$y");
        if($hex == NULL){
            throw new BgaUserException(_("Invalid path!"));
        }
    }

    function checkPath(array $start, array $moves, $finalCheck, $pathCheck): array {
        $checks = [];
        foreach (array_map(fn($move) => HEX_DIRECTIONS[$move], $moves) as [$dx, $dy]) {
            $start[0] += $dx;
            $start[1] += $dy;
            $pathCheck($start[0], $start[1]);
        }

        $finalCheck($start[0], $start[1]);
        return [$start[0], $start[1]];
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
        for($i = 0; ($i/8)<4; $i += 8){
            $wolf_id = ($moved_wolves & (0xff << $i)) >> $i;
            if($wolf_id > 0){
                $wolf_ids[] = $wolf_id;
            }
        }

        return $wolf_ids;

    }

    function flipTiles(int $playerId, array $tileIndices, bool $forceFlip=false): int {
        $tiles = $this->getPlayerTiles($playerId);
        self::dump("player_$playerId\_tiles", $tiles);
        $terrain = -1;
        $sets = [];

        foreach ($tileIndices as $tileIndex) {
            $nextTerrain = $tileIndex < TILE_TERRAIN_TYPES ?
                ($tileIndex + $tiles[strval($tileIndex)]) % TILE_TERRAIN_TYPES :
                (int)self::getUniqueValueFromDB("SELECT home_terrain FROM player_status WHERE player_id = $playerId");

            self::debug("Flipping tile at index ($tileIndex) of type ($nextTerrain)");
            if ($forceFlip || ($terrain >= 0 && $nextTerrain !== $terrain)) {
                throw new BgaUserException(_('All tiles must have identical terrain'));
            }
            $terrain = $nextTerrain;

            if ($tileIndex < TILE_TERRAIN_TYPES) {
                $sets[] = "`$tileIndex` = 1 - `$tileIndex`";
            }
        }

        if (count($sets) > 0) {
            $update = implode(", ", $sets);
            $query = "UPDATE player_tiles SET $update WHERE player_id = $playerId";
            self::DbQuery($query);
        }

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
            || $validityCheck['kinds'][0] === P_DEN 
            || $validityCheck['owners'][0] === $playerId);
    }

    function getDenAwards(int $denType, int $deployedDens): ?int {
        switch(DEN_COLS[$denType]){
            case 'howl':
                return HOWL_RANGE_AWARDS[self::getPlayersNumber()][$deployedDens];
            case 'pack':
                return PACK_SPREAD_AWARDS[self::getPlayersNumber()][$deployedDens];
            case 'speed':
                return WOLF_SPEED_AWARDS[self::getPlayersNumber()][$deployedDens];
        }
        return null;
    }

    function getRegionPresence(int $regionId): array {
        $lair = P_LAIR;
        $den = P_DEN;
        $alpha = P_ALPHA;
        $pack = P_PACK;
        //TODO: Ensure this query works
        $query = <<<EOF
                    SELECT p.owner as owner, 
                    ((COUNT(CASE WHEN p.kind=$lair THEN 1 END) * 3) + COUNT(CASE WHEN p.kind=$den OR p.kind=$pack OR p.kind=$alpha THEN 1 END)) as score,
                    COUNT(CASE WHEN p.kind=$alpha THEN 1 END) as alphas
                    FROM land l
                    NATURAL LEFT JOIN pieces p
                    WHERE l.region_id=$regionId
                    GROUP BY p.owner
                    ORDER BY score, alphas
                    EOF;
        return self::getObjectListFromDB($query);
    }

    function doHunt(): void {
        $preyType = P_PREY;
        $preyTokens = self::getObjectListFromDB("SELECT DISTINCT x, y, prey_metadata FROM pieces WHERE kind=$preyType");

        foreach($preyTokens as ["x" => $x, "y" => $y, "prey_metadata" => $preyData]){
            $args = [];
            foreach (array_map(fn($move) => HEX_DIRECTIONS[$move], $moves) as [$dx, $dy]) {
                $newX = $x + $dx;
                $newY = $y + $dy;
                $args[] = "(x=$newX AND y=$newY)";
            }

            $condition = implode(" OR ", $args);

            $preyKind = P_PREY;
            $alphaKind = P_ALPHA;
            $query = <<<EOF
                        SELECT p.owner as player_id
                        FROM player_status s
                        LEFT JOIN pieces p ON s.player_id = p.owner
                        WHERE $condition
                        AND s.prey_data & $preyData = 0
                        AND p.kind IN ($preyKind, $alphaKind)
                        GROUP BY p.owner
                        HAVING COUNT(DISTINCT p.x, p.y) >= 3
                        EOF;
            
            $playerPresence = self::getObjectListFromDB($query);

            foreach($playerPresence as ["player_id" => $playerId]){
                self::DbQuery("UPDATE player_status SET turn_tokens=turn_tokens + 1, prey_data = prey_data | $preyData WHERE player_id=$playerId");
            }
        }
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

        $kinds = [P_ALPHA, P_PACK];
        $values = array_map(fn($piece) => "($x, $y, $player_id, $piece)", $kinds);
        $args = implode(", ", $values);
        $query = "INSERT INTO pieces (x, y, owner, kind) VALUES $args";
        self::DbQuery($query);

        $insertId = self::DbGetLastId();
        $this->notifyAllPlayers('draft', clienttranslate('${player_name} placed initial 🐺.'), [
            'player_name' => self::getActivePlayerName(),
            'player_id' => $player_id,
            'x' => $x,
            'y' => $y,
            'ids' => [$insertId - 1, $insertId],
            'kinds' => $kinds
        ]);

        $this->gamestate->nextState('draftPlace');
    }

    function selectAction(int $action, array $tiles, int $bonusTerrain, ?int $forceTerrain = null): void {
        self::checkAction('selectAction');
        if (!array_key_exists($action, ACTION_COSTS)) {
            throw new BgaUserException(_('Invalid action selected'));
        }

        $playerId = self::getActivePlayerId();

        if ($bonusTerrain >= self::getUniqueValueFromDb("SELECT terrain_tokens FROM player_status WHERE player_id = $playerId")) {
            throw new BgaUserException(_('Not enough bonus terrain tokens'));
        }

        $cost = ACTION_COSTS[$action];
        if (count($tiles) + $bonusTerrain != $cost) {
            throw new BgaUserException(_('${count} tile(s) need to be flipped for this action'));
        }

        if($forceTerrain && count($tiles) > 0){
            throw new BgaUserException(_('Cannot force terrain when flipping tiles'));
        }

        $terrain = $forceTerrain ?? $this->flipTiles($playerId, $tiles);

        $flipTilesState = 0;
        foreach($tiles as $tileIndex){
            $flipTilesState |= (1 << $tileIndex);
        }

        $this->setGameStateValue(G_FLIPPED_TILES, $flipTilesState);
        $this->setGameStateValue(G_SPENT_TERRAIN_TOKENS, $bonusTerrain);

        $actionName = $this->actionNames[$action];
        switch($actionName){
            case 'move':
                $this->setGameStateValue(G_MOVED_WOLVES, 0);
                $deployedDens = self::getUniqueValueFromDB("SELECT deployed_pack_dens FROM player_status WHERE player_id=$playerId");
                $this->setGameStateValue(G_MOVES_REMAINING, PACK_SPREAD[$deployedDens]);
                break;
            case 'howl':
                $deployedWolves = self::getUniqueValueFromDB("SELECT deployed_wolves FROM player_status WHERE player_id=$playerId");
                if($deployedWolves > count(WOLF_DEPLOYMENT)){
                    throw new BgaUserException(_('You have no wolves to deploy!'));
                }
                break;
            case 'den':
                $deployedDens = self::getUniqueValueFromDB("SELECT (deployed_howl_dens + deployed_pack_dens + deployed_speed_dens) as deployed_dens FROM player_status WHERE player_id=$playerId");
                if($deployedDens >= count(HOWL_RANGE) + count(PACK_SPREAD) + count(WOLF_SPEED)){
                    throw new BgaUserException(_('You have no dens to deploy!'));
                }
                break;
            default:
                break;
        }

        if($bonusTerrain){
            $query = "UPDATE player_status SET terrain_tokens = terrain_tokens - $bonusTerrain WHERE player_id = $playerId";
            self::DbQuery($query);
        }

        $this->setGameStateValue(G_SELECTED_TERRAIN, $terrain);

        $this->notifyAllPlayers('action', clienttranslate('${player_name} chooses to ${action_name} at ${terrain_name}.'), [
            'player_name' => self::getActivePlayerName(),
            'player_id' => $playerId,
            'action_name' => $this->actionNames[$action],
            'terrain_name' => $this->terrainNames[$terrain],
            'new_tiles' => $this->getPlayerTiles($playerId)
        ]);


        $this->gamestate->nextState( $actionName . "Select");
    }

    function move(int $wolfId, array $path): void {
        self::checkAction('move');

        $movedWolves = $this->getMovedWolves();
        
        if(in_array($wolfId, $movedWolves)){
            throw new BgaUserException(_('This wolf has already been moved this turn!'));
        }

        $playerId = self::getActivePlayerId();
        $terrain_type = $this->getGameStateValue(G_SELECTED_TERRAIN);
        $query = "SELECT * FROM pieces WHERE id=$wolfId";
        $wolf = self::getObjectFromDB($query);
        $isAlpha = (int)$wolf['kind'] === P_ALPHA;
        if($wolf === NULL || $wolf['owner'] != $playerId || $wolf['kind'] > P_PACK){
            throw new BgaUserException(_('The wolf you selected is not valid!'));
        }

        //Verify move is valid

        $deployedDens = self::getUniqueValueFromDB("SELECT deployed_speed_dens FROM player_status WHERE player_id=$playerId");
        $max_distance = WOLF_SPEED[$deployedDens];
        if(count($path) > $max_distance){
            throw new BgaUserException(_('The selected tile is out of range'));
        }

        $pathCheck = function($x, $y) {
            $hex = self::getObjectFromDB("SELECT * FROM land WHERE x=$x AND y=$y");
            if($hex === NULL || $hex['terrain'] === T_WATER){
                throw new BgaUserException(_('Cannot find a clear path to the given tile'));
            } 
        };

        $finalCheck = function($x, $y) use ($isAlpha, $playerId, $terrain_type) {
            $finalHex = self::getObjectFromDB("SELECT * FROM land WHERE x=$x AND y=$y");
            if($finalHex['terrain'] != $terrain_type){
                throw new BgaUserException(_('Invalid terrain for destination!'));
            }
            $finalPieces = self::getObjectListFromDB("SELECT * FROM pieces WHERE x=$x AND y=$y");
            switch(count($finalPieces)){
                case 0:
                    break;
                case 1:
                    ['owner' => $owner, 'kind' => $kind] = $finalPieces[0];
                    if($owner !== $playerId && (int)$kind !== P_DEN && (!$isAlpha || (int)$kind !== P_PACK)){
                        throw new BgaUserException(_('Invalid move location'));
                    }
                    break;
                default:    
                    throw new BgaUserException(_('Hex is full!'));
            }
        };

        [$targetX, $targetY] = $this->checkPath([$wolf['x'], $wolf['y']], $path, $finalCheck, $pathCheck);

        $query = "SELECT * FROM pieces WHERE x=$targetX AND y=$targetY AND kind=1 AND owner != $playerId";
        $potential_wolves = self::getObjectListFromDB($query);

        $query = "UPDATE pieces SET x=$targetX, y=$targetY WHERE id=$wolfId";
        self::DbQuery($query);
        $this->addMovedWolf($wolf['id']);
        $newVal = $this->incGameStateValue(G_MOVES_REMAINING, -1);

        $wolfName = $wolf['kind'] == P_PACK ? " Pack" : "n Alpha";
        self::notifyAllPlayers('update', clienttranslate('${player_name} has moved a' . $wolfName . ' wolf, to a ${terrain} tile'),
        [
            'player_name' => self::getActivePlayerName(),
            'newPiece' => [
                'id' => $wolfId,
                'owner' => $playerId,
                'x' => $targetX,
                'y' => $targetY,
                'kind' => $wolf['kind']
            ],
            'terrain' => $terrain_type,
        ]);
        if(count($potential_wolves) > 0){
            $pack_wolf = $potential_wolves[0];
            $this->setGameStateValue(G_DISPLACEMENT_WOLF, $pack_wolf['id']);
            $this->gamestate->nextState(TR_DISPLACE);
        }
        else{
            $this->gamestate->nextState(($newVal > 0) ? TR_MOVE : TR_END_MOVE);
        }  
    }

    function getMaxDisplacement(int $x, int $y, int $playerId): int {
            $water = T_WATER;
            $query = <<<EOF
                SELECT {$this->sql_hex_range('l.x', 'l.y', $x, $y)} AS dist
                FROM land l NATURAL LEFT JOIN pieces p
                WHERE l.terrain <> $water
                AND (SELECT COUNT(*) FROM pieces WHERE x = l.x AND y = l.y) < 2
                AND (p.kind IS NULL OR p.owner = $playerId)
                ORDER BY dist
                LIMIT 1
                EOF;
            return self::getUniqueValueFromDB($query) ?? -1;
    }

    function displace(array $path): void {
        self::checkAction('displace');
        $playerId = self::getActivePlayerId();
        $wolfId = $this->getGameStateValue(G_DISPLACEMENT_WOLF);
        $wolf = self::getObjectFromDB("SELECT * FROM pieces WHERE id=$wolfId");
        $range = $this->getMaxDisplacement($wolf['x'], $wolf['y'], $playerId);
        $finalCheck = function($x, $y) use ($playerId, $range, $wolf) {
            $dist = (abs($wolf['x'] - $x) + abs($wolf['y'] - $y) + abs($x - $y - $wolf['x'] + $wolf['y'])) / 2;
            if($dist > $range){
                throw new BgaUserException(_('Tile is too far'));
            }
            $query = <<<EOF
                SELECT l.*
                FROM land l
                LEFT JOIN pieces p ON l.x = p.x AND l.y = p.y
                WHERE l.terrain <> 5
                AND (SELECT COUNT(*) FROM pieces WHERE x = l.x AND y = l.y) < 2
                AND (p.kind IS NULL OR p.owner <=> $playerId)
                AND l.x = $x AND l.y = $y
                EOF;

            $land = self::getObjectFromDB($query);
            if($land === NULL){
                throw new BgaUserException(_('Cannot move wolf to this tile'));
            }

        };
        [$x, $y] = $this->checkPath([$wolf['x'], $wolf['y']], $path, $finalCheck, [$this, "validityCheck"]);

        self::DbQuery("UPDATE pieces SET x=$x, y=$y WHERE id=$wolfId");

        $targetId = $wolf['owner'];
        self::notifyAllPlayers('update', clienttranslate('${player_name} has displaced a${wolf_string} wolf belonging to ${target_player}.'),
        [
            'player_name' => self::getActivePlayerName(),
            'newPiece' => [
                'id' => $wolfId,
                'owner' => $wolf['owner'],
                'x' => $x,
                'y' => $y,
                'kind' => $wolf['kind']
            ],
            'wolf_string' => $wolf['kind'] == P_PACK ? ' Pack' : 'n Alpha',
            'target_player' => self::getPlayerNameById($targetId),
        ]);
        $remainingMoves = $this->getGameStateValue(G_MOVES_REMAINING);
        $this->gamestate->nextState($remainingMoves > 0 ? TR_MOVE : TR_POST_ACTION);
    }

    function howl(int $wolfId, int $x, int $y): void {
        self::checkAction('howl');
        $playerId = self::getActivePlayerId();
        $terrain = $this->getGameStateValue(G_SELECTED_TERRAIN);
        $wolf = self::getObjectFromDB("SELECT * FROM pieces WHERE id=$wolfId");

        ['dens' => $deployedDens, 'wolves' => $wolfIndex] = self::getObjectFromDB(<<<EOF
            SELECT deployed_howl_dens AS dens, deployed_wolves AS wolves  
            FROM player_status WHERE player_id=$playerId
            EOF);

        if($wolf == NULL || $wolf['kind'] != P_ALPHA || $wolf['owner'] != $playerId){
            throw new BgaUserException(_('Invalid wolf!'));
        }

        $lone = P_LONE;
        $maxRange = HOWL_RANGE[$deployedDens];
        $newKind = WOLF_DEPLOYMENT[$wolfIndex];

        self::DbQuery(<<<EOF
            UPDATE pieces NATURAL JOIN land
            SET kind=$newKind, owner=$playerId
            WHERE x = $x AND y = $y AND kind = $lone AND terrain = $terrain 
              AND {$this->sql_hex_in_range($x, $y, $wolf['x'], $wolf['y'], $maxRange)}
            EOF);

        if (self::DbAffectedRow() <= 0) {
            throw new BgaUserException(_('Selected tile is invalid'));
        }

        $updateId = self::getUniqueValueFromDb("SELECT id FROM pieces WHERE x = $x AND y = $y");

        self::DbQuery("INSERT INTO moonlight_board (kind) VALUES ($lone)");
        self::DbQuery("UPDATE player_status SET deployed_wolves=deployed_wolves + 1 WHERE player_id=$playerId");

        self::notifyAllPlayers('update', clienttranslate('${player_name} has howled at a Lone Wolf'), [
            'player_name' => self::getActivePlayerName(),
            'newPiece' => [
                'id' => $updateId,
                'owner' => $playerId,
                'x' => $x,
                'y' => $y,
                'kind' => $newKind,
                'progress' => true
            ]
        ]);
        $this->gamestate->nextState(TR_POST_ACTION);

        /*$finalCheck = function($x, $y) use ($playerId, $wolf, $max_range){
            $lone_val = P_LONE;
            $wolfX = $wolf['x'];
            $wolfY = $wolf['y'];
            $query = "SELECT l.* FROM land l NATURAL LEFT JOIN pieces p WHERE l.x=$x AND l.y=$y AND p.kind<=>$lone_val AND (ABS(l.x - $wolfX) + ABS(l.y - $wolfY) + ABS(l.x - l.y - $wolfX + $wolfY)) / 2 <= $max_range";
            $validLand = self::getObjectFromDB($query);
            if($validLand === NULL){
                throw new BgaUserException(_('Selected tile is invalid'));
            }
        };
        [$x, $y] = $this->checkPath([$wolf['x'], $wolf['y']], $path, $finalCheck);*/
    }

    function placeDen(int $wolfId, ?int $path, int $denType): void {
        self::checkAction('den');

        $playerId = self::getActivePlayerId();
        $denCol = 'deployed_'.DEN_COLS[$denType].'_dens';
        $numDens = self::getUniqueValueFromDB("SELECT $denCol FROM player_status WHERE player_id=$playerId");
        if($numDens >= 4){
            throw new BgaUserException(_('No more dens of this type!'));
        }
        $terrain_type = $this->getGameStateValue(G_SELECTED_TERRAIN);
        $wolf = self::getObjectFromDB("SELECT * FROM pieces WHERE id=$wolfId");
        if($wolf === NULL || (int)$wolf['kind'] !== P_ALPHA || $wolf['owner'] !== $playerId){
            throw new BgaUserException(_('Invalid wolf selected!'));
        }

        $denValue = P_DEN;
        $x = (int)$wolf['x'];
        $y = (int)$wolf['y'];
        if ($path !== null) {
            [$dx, $dy] = HEX_DIRECTIONS[$path];
            $x += $dx;
            $y += $dy;
        }

        $query = <<<EOF
            SELECT COUNT(*)  
            FROM land l NATURAL LEFT JOIN pieces p
            WHERE l.x = $x AND l.y = $y 
                AND l.terrain = $terrain_type
                AND (SELECT COUNT(*) FROM pieces WHERE x=l.x AND y=l.y) < 2
                AND (p.owner IS NULL OR p.kind < $denValue)                    
            EOF;
        if (self::getUniqueValueFromDB($query) === 0){
            throw new BgaUserException(_('Invalid hex selected!'));
        }

        $deployedDens = self::getUniqueValueFromDB("SELECT $denCol FROM player_status WHERE player_id=$playerId");

        $award = $this->getDenAwards($denType, $deployedDens);
        $rewardString = "";
        switch($award){
            case AW_TERRAIN:
                $rewardString = ", terrain_tokens=terrain_tokens + 1";
                break;
            case AW_TURN:
                $rewardString = ", turn_tokens=turn_tokens + 1";
                break;
            default:
                break;
        }
        self::DbQuery("INSERT INTO pieces (owner, kind, x, y) VALUES ($playerId, $denValue, $x, $y)");
        $newId = self::DbGetLastId();
        self::DbQuery("UPDATE player_status SET $denCol=$denCol + 1$rewardString WHERE player_id=$playerId");

        self::notifyAllPlayers('update', clienttranslate('${player_name} placed a den, from their ${den_type} track'),
        [
            'player_name' => self::getActivePlayerName(),
            'newPiece' => [
                'id' => $newId,
                'owner' => $playerId,
                'x' => $x,
                'y' => $y,
                'kind' => P_DEN
            ],
            'newAttributes' => [
                'playerId' => $playerId,
                $denCol => $deployedDens + 1
            ],
            'den_type' => DEN_COLS[$denType],
        ]);
        $this->gamestate->nextState(TR_POST_ACTION);

    }

    function placeLair(int $wolfId, ?int $path): void {
        self::checkAction('lair');

        $playerId = self::getActivePlayerId();
        $numLairs = self::getUniqueValueFromDB("SELECT deployed_lairs FROM player_status WHERE player_id=$playerId");
        if($numLairs >= 4){
            throw new BgaUserException(_('No more lairs!'));
        }
        $terrain = $this->getGameStateValue(G_SELECTED_TERRAIN);
        $wolf = self::getObjectFromDB("SELECT * FROM pieces WHERE id=$wolfId");
        if($wolf === NULL || (int)$wolf['kind'] !== P_ALPHA || $wolf['owner'] !== $playerId){
            throw new BgaUserException(_('Invalid wolf selected!'));
        }

        $lairValue = P_LAIR;
        $x = (int)$wolf['x'];
        $y = (int)$wolf['y'];
        if ($path !== null) {
            [$dx, $dy] = HEX_DIRECTIONS[$path];
            $x += $dx;
            $y += $dy;
        }

        $den = P_DEN;
        $water = T_WATER;
        $query = <<<EOF
            SELECT id 
            FROM pieces NATURAL LEFT JOIN land
            WHERE x = $x AND y = $y
                AND terrain = $terrain
                AND owner = $playerId 
                AND kind = $den
                AND (SELECT COUNT(*) 
                    FROM land 
                    WHERE x BETWEEN $x - 1 AND $x + 1
                        AND y BETWEEN $y - 1 AND $y + 1
                        AND {$this->sql_hex_in_range('x', 'y', $x, $y, 1)}
                    AND terrain=$water) > 0
            EOF;
        $updateId = self::getUniqueValueFromDB($query);
        if($updateId === NULL){
            throw new BgaUserException(_('Invalid hex selected!'));
        }

        $pieces = self::getObjectListFromDB("SELECT * FROM pieces WHERE x=$x and y=$y AND kind < $den AND owner <> $playerId");
        
        self::DbQuery("UPDATE pieces SET kind=$lairValue WHERE x=$x AND y=$y AND kind=$den");

        self::DbQuery("UPDATE player_status SET deployed_lairs=deployed_lairs + 1, terrain_tokens=terrain_tokens + 1 WHERE player_id=$playerId");

        self::DbQuery("INSERT INTO moonlight_board (kind, player_id) VALUES ($den, $playerId)");

        self::notifyAllPlayers('update', clienttranslate('${player_name} has placed a lair'), [
            'player_name' => self::getActivePlayerName(),
            'newPiece' => [
                'id' => $updateId,
                'owner' => $playerId,
                'x' => $x,
                'y' => $y,
                'kind' => P_LAIR,
                'progress' => true
            ]
        ]);
        if(count($pieces) == 1){
            $moveWolf = $pieces[0];
            $this->setGameStateValue(G_DISPLACEMENT_WOLF, $moveWolf['id']);
            $this->setGameStateValue(G_MOVES_REMAINING, 0);
            $this->gamestate->nextState(TR_DISPLACE);
            return;
        }
        
        $this->gamestate->nextState(TR_POST_ACTION);

    }

    function dominate(int $wolfId, int $targetId, int $denType): void {
        self::checkAction('dominate');
        $playerId = self::getActivePlayerId();
        $terrain = $this->getGameStateValue(G_SELECTED_TERRAIN);
        $wolf = self::getObjectFromDB("SELECT * FROM pieces WHERE id=$wolfId");
        $deployedDens = self::getUniqueValueFromDB("SELECT deployed_howl_dens FROM player_status WHERE player_id=$playerId");
        $maxRange = HOWL_RANGE[$deployedDens];

        if($wolf === NULL || $wolf['owner'] !== $playerId || (int)$wolf['kind'] !== P_ALPHA){
            throw new BgaUserException(_('Invalid wolf!'));
        }

        [$pack, $den] = [P_PACK, P_DEN];
        $target = self::getObjectFromDB(<<<EOF
            SELECT * FROM pieces AS target NATURAL JOIN land
            WHERE id=$targetId AND owner <> $playerId 
                AND terrain = $terrain AND kind IN ($pack, $den)
                AND {$this->sql_hex_in_range('x', 'y', $wolf['x'], $wolf['y'], $maxRange)}
                AND (SELECT COUNT(*) FROM pieces WHERE x = target.x AND y = target.y AND owner = target.owner) = 1
            EOF);
        if ($target === null) {
            throw new BgaUserException(_('Selected target is invalid!'));
        }

        if((int)$target['kind'] === P_DEN){
            if(!array_key_exists($denType, DEN_COLS)){
                throw new BgaUserException(_('Must specify a den type if replacing a den!'));
            }
            $denName = 'deployed_'.DEN_COLS[$denType].'_dens';
            $numDens = self::getUniqueValueFromDB("SELECT $denName FROM player_status WHERE player_id=$playerId");
            if($numDens >= 4){
                throw new BgaUserException(_('You have no more dens of this type to deploy!'));
            }

            $award = $this->getDenAwards($denType, $numDens);
            $rewardString = "";
            switch($award){
                case AW_TERRAIN:
                    $rewardString = ", terrain_tokens=terrain_tokens + 1";
                    break;
                case AW_TURN:
                    $rewardString = ", turn_tokens=turn_tokens + 1";
                    break;
            }

            self::DbQuery("UPDATE player_status SET $denName=$denName + 1$rewardString WHERE player_id=$playerId");
            $newKind = P_DEN;
        } else {
            $numWolves = self::getUniqueValueFromDB("SELECT deployed_wolves FROM player_status WHERE player_id=$playerId");
            if($numWolves >= 8){
                throw new BgaUserException(_('You have no more wolves you can deploy!'));
            }
            $wolfIndex = self::getUniqueValueFromDB("SELECT deployed_wolves FROM player_status WHERE player_id=$playerId");
            self::DbQuery("UPDATE player_status SET deployed_wolves=deployed_wolves + 1 WHERE player_id=$playerId");
            $wolfType = WOLF_DEPLOYMENT[$wolfIndex];
            $newKind = $wolfType;
        }

        self::DbQuery("UPDATE pieces SET owner=$playerId, kind=$newKind WHERE id=$targetId");
        self::DbQuery("INSERT INTO moonlight_board (player_id, kind) VALUES ({$target['owner']}, {$target['kind']})");

        self::notifyAllPlayers('update', clienttranslate('${player_name} has dominated a piece belonging to ${target_player}'),
            [
                "player_id" => $playerId,
                "player_name" => self::getActivePlayerName(),
                'newPiece' => [
                    'id' => $target['id'],
                    'owner' => $playerId,
                    'x' => $target['x'],
                    'y' => $target['y'],
                    'kind' => $newKind,
                    'progress' => true
                ],
                "target_player" => self::getPlayerNameById($target['owner'])
            ]);
        $this->gamestate->nextState(TR_POST_ACTION);

        /*$finalCheck = function($x, $y) use ($playerId, $wolf, $max_range, $target){

            if(!($target['x'] === $x && $target['y'] === $y)){
                throw new BgaUserException(_('Target wolf is not at the end of the given path!'));
            }
            $wolves_max = P_PACK;
            $wolfX = $wolf['x'];
            $wolfY = $wolf['y'];
            $query = <<<EOF
                        SELECT l.* 
                        FROM land l 
                        NATURAL LEFT JOIN pieces p 
                        WHERE l.x=$x AND l.y=$y
                        AND NOT (p.owner IS NULL OR p.owner <=> $playerId)
                        AND ((SELECT COUNT(*) FROM pieces WHERE x=l.x AND y=l.y) < 2 OR (SELECT COUNT(*) FROM pieces WHERE x=l.x AND y=l.y GROUP BY owner) <> 1)
                        AND {$this->sql_hex_in_range('l.x', 'l.y', $wolfX, $wolfY, $max_range)}
            EOF;
            $validLand = self::getObjectFromDB($query);
            if($validLand === NULL){
                throw new BgaUserException(_('Selected tile is invalid'));
            }
        };
        $this->checkPath([$wolf['x'], $wolf['y']], $path, $finalCheck, [$this, "validityCheck"]);*/
    }

    function extraTurn(){
        self::checkAction('extraTurn');

        $playerId = self::getActivePlayerId();
        $turnTokens = self::getUniqueValueFromDB("SELECT turn_tokens FROM player_status WHERE player_id=$playerId");
        if($turnTokens == 0){
            throw new BgaUserException(_('You have no extra turn tokens to play!'));
        }
        self::DbQuery("UPDATE player_status SET turn_tokens=$turnTokens - 1 WHERE player_id=$playerId");
        $this->incGameStateValue(G_MOVES_REMAINING, 1);

        self::notifyAllPlayers(NOT_EXTRA_TURN, clienttranslate('${active_player} has decided to take an additional turn'),
        [
            "player_id" => $playerId,
            "active_player" => self::getActivePlayerName()
        ]);
        $this->gamestate->nextState(TR_SELECT_ACTION);
    }

    function endTurn(){
        self::checkAction('endTurn');
        $playerId = self::getActivePlayerId();
        self::notifyAllPlayers(NOT_END_TURN, clienttranslate('${active_player} has ended their turn'),
        [
            "player_id" => $playerId,
            "active_player" => self::getActivePlayerName()
        ]);
        $this->gamestate->nextState(TR_CONFIRM_END);
    }

    function undo(){
        self::checkAction("undo");
        $this->gamestate->jumpToState(ST_ACTION_SELECTION);
    }

//////////////////////////////////////////////////////////////////////////////
//////////// Game state arguments
////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */

    function argsMove(){
        return [
            'numMoves' => $this->getGameStateValue(G_MOVES_REMAINING),
            'selectedTerrain' => $this->getGameStateValue(G_SELECTED_TERRAIN),
            'movedWolves' => $this->getMovedWolves()
        ];
    }

    function argActionSelection(){
        $returnArr = [];
        $playerId = self::getActivePlayerId();
        $returnArr["remainingActions"] = $this->getGameStateValue(G_ACTIONS_REMAINING);
        return $returnArr;
    }

    function argTerrain(){
        return [
            "selectedTerrain" => $this->getGameStateValue(G_SELECTED_TERRAIN)
        ];
    }

    function argDisplaceSelection(){
        return [
            "displacementWolf" => $this->getGameStateValue(G_DISPLACEMENT_WOLF)
        ];
    }

    function argConfirmEnd(){
        $playerId = self::getActivePlayerId();
        return [
            "remainingTokens" => self::getUniqueValueFromDB("SELECT turn_tokens FROM player_status WHERE player_id=$playerId")
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

        $this->gamestate->nextState($draftCompleted ? TR_DRAFT_END : TR_DRAFT_CONTINUE);
    }

    function stPostAction(): void {
        $this->doHunt();
        $remainingActions = $this->incGameStateValue(G_ACTIONS_REMAINING, -1);
        $this->gamestate->nextState($remainingActions == 0 ? TR_CONFIRM_END : TR_SELECT_ACTION);
    }

    function stNextTurn(): void {

        //Scoring
        $numPlayers = self::getPlayersNumber();
        $currentDate = self::getUniqueValueFromDB("SELECT COUNT(*) FROM moonlight_board");
        $currentPhase = $this->getGameStateValue(G_MOON_PHASE);
        $phaseDate = PHASES[$currentPhase];

        if($currentDate >= $phaseDate){
            $this->incGameStateValue(G_MOON_PHASE, 1);

            //Determine condition for region
            $phaseBitMask = [M_CRESCENT, M_QUARTER, M_FULL][$currentPhase];

            $scoringRegions = self::getObjectListFromDB("SELECT * FROM regions WHERE moon_phase & $phaseBitMask > 0");
            $score = ($currentPhase * 2) + 4;
            $winners = [];
            foreach($scoringRegions as $region){

                //Gather region presence and sort based off most score, then most alphas
                $presence = $this->getRegionPresence($region['region_id']);

                //If no one is in the region, no one scores
                if(count($presence) == 0){
                    continue;
                }
                $cmp = function($a, $b) {
                    if($a['score'] === $b['score']){
                        return ($a['alphas'] > $b['alphas']) ? -1 : 1;
                    }
                    return ($a['score'] > $b['score']) ? -1 : 1;
                };
                usort($presence, $cmp);

                //Determine who won

                //At least 2 people in region
                if(count($presence) > 1){

                    //Determine how many players won first place
                    $firstWinner = $presence[0];
                    $winners = array_filter($presence, fn($thisPlayer) => $firstWinner['score'] === $thisPlayer['score'] && $firstWinner['alphas'] === $thisPlayer['alphas']);

                    //multiple winners, no second place, and everyone gets half score
                    if(count($winners) > 1){
                        foreach($winners as $winner){
                            self::DbQuery("UPDATE player SET score = score + $score / 2 WHERE player_id=$winner");
                        }
                    }
                    //If one winner, maybe second place?
                    else{

                        //Set first player points/score token
                        self::DbQuery("UPDATE player SET score=score+$score WHERE player_id={$presence[0]['owner']}");
                        self::DbQuery("INSERT INTO score_token (player_id, type) VALUES ({$presence[0]['owner']}, $currentPhase)");

                        //Only 2 people in region, second place is guaranteed
                        if(count($presence) === 2){
                            self::DbQuery("UPDATE player SET score=score + $score / 2 WHERE player_id={$presence[1]['owner']}");
                        }
                        //Otherwise, there can only be one player who wins second place
                        else if($presence[1]['points'] !== $presence[2]['points'] && $presence[1]['alphas'] !== $presence[2]['alphas']){
                            self::DbQuery("UPDATE player SET score=score + $score / 2 WHERE player_id={$presence[1]['owner']}");
                        }
                    }
                }
                //Only 1 person with presence in region, so they win first place
                else{
                    $winner = $presence[0]['owner'];
                    self::DbQuery("UPDATE player SET score=score+$score WHERE player_id=$winner");
                    self::DbQuery("INSERT INTO score_token (player_id, type) VALUES ($winner, $currentPhase)");
                }
                
            }
        }

        //Determine if the game should end
        if($currentPhase == 2){
            $this->gamestate->nextState(TR_END_GAME);
        }
        else{
            $this->activeNextPlayer();
            $this->setGameStateValue(G_ACTIONS_REMAINING, 2);
            $this->gamestate->nextState(TR_START_TURN);
        }
    }

    function stPreActionSelection(): void {
        $this->setGameStateValue(G_SELECTED_TERRAIN, -1);
        $this->setGameStateValue(G_MOVES_REMAINING, -1);
        $this->setGameStateValue(G_MOVED_WOLVES, 0);
        $this->setGameStateValue(G_DISPLACEMENT_WOLF, -1);
        $this->setGameStateValue(G_FLIPPED_TILES, 0);
        $this->setGameStateValue(G_SPENT_TERRAIN_TOKENS, 0);
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
