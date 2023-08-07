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

    function __construct() {
        // Your global variables labels:
        //  Here, you can assign labels to global variables you are using for this game.
        //  You can use any number of global variables with IDs between 10 and 99.
        //  If your game has options (variants), you also have to associate here a label to
        //  the corresponding ID in gameoptions.inc.php.
        // Note: afterwards, you can get/set the global variables with getGameStateValue/setGameStateInitialValue/setGameStateValue
        parent::__construct();
        
        self::initGameStateLabels([
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

        self::setGameStateInitialValue(G_SELECTED_TERRAIN, -1);
        self::setGameStateInitialValue(G_ACTIONS_REMAINING, 2);
        self::setGameStateInitialValue(G_MOVES_REMAINING, -1);
        self::setGameStateInitialValue(G_MOVED_WOLVES, 0);
        self::setGameStateInitialValue(G_DISPLACEMENT_WOLF, -1);
        self::setGameStateInitialValue(G_MOON_PHASE, 0);
        self::setGameStateInitialValue(G_FLIPPED_TILES, 0);
        self::setGameStateInitialValue(G_SPENT_TERRAIN_TOKENS, 0);

        $playerStats = [
            STAT_PLAYER_PREY_HUNTED,
            STAT_PLAYER_TURNS_PLAYED,
            STAT_PLAYER_WOLVES_MOVED,
            STAT_PLAYER_LONE_WOLVES_CONVERTED,
            STAT_PLAYER_DENS_PLACED,
            STAT_PLAYER_DENS_UPGRADED,
            STAT_PLAYER_DENS_DOMINATED,
            STAT_PLAYER_WOLVES_DOMINATED,
            STAT_PLAYER_BONUS_ACTIONS_TAKEN,
            STAT_PLAYER_FIRST_PLACE,
            STAT_PLAYER_SECOND_PLACE,
            STAT_PLAYER_TERRAIN_TOKENS_SPENT
        ];
        foreach($playerStats as $stat){
            self::initStat("player", $stat, 0);
        }

        $tableStats = [
            STAT_TURNS_TAKEN,
            STAT_BONUS_ACTIONS_TAKEN,
            STAT_TERRAIN_TOKENS_SPENT
        ];
        foreach($tableStats as $stat){
            self::initStat("table", $stat, 0);
        }

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
        shuffle($moonPhases);

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

        if($player_count === 2){
            $this->generateAIPieces();
            $currentPlayer = self::getActivePlayerId();
            self::DbQuery("UPDATE player_status SET turn_tokens=turn_tokens+1 WHERE player_id <> $currentPlayer");
        }
    }

    function generateAIPieces(){
        $regions = self::getObjectListFromDB("SELECT region_id, moon_phase, center_x, center_y FROM regions");
        $values = [];
        foreach($regions as ["region_id" => $regionId, "moon_phase" => $moonPhase, "center_x" => $x, "center_y" => $y]){
            $regionId = (int)$regionId;
            $moonPhase = (int)$moonPhase;
            $water = T_WATER;

            $x = (int)$x;
            $y = (int)$y;
            $topRight = [$x + HD_TOP_RIGHT[0], $y + HD_TOP_RIGHT[1]];
            $bottomRight = [$x + HD_BOTTOM_RIGHT[0], $y + HD_BOTTOM_RIGHT[1]];
            $piecesToAdd = [];
            switch($moonPhase){
                case M_CRESCENT:
                    $piecesToAdd[] = ["kind" => P_PACK, "loc" => $topRight, "num" => 2];
                    break;
                case M_CRES_HALF:
                    $piecesToAdd[] = ["kind" => P_LAIR, "loc" => $topRight, "num" => 1];
                    break;
                case M_FULL:
                    $piecesToAdd[] = ["kind" => P_ALPHA, "loc" => $topRight, "num" => 1];
                    $piecesToAdd[] = ["kind" => P_LAIR, "loc" => $topRight, "num" => 1]; 
                    break;
                case M_QUARTER_FULL:
                    $piecesToAdd[] = ["kind" => P_LAIR, "loc" => $topRight, "num" => 1];
                    $piecesToAdd[] = ["kind" => P_ALPHA, "loc" => $bottomRight, "num" => 2];
                default:
                    break;
            }
            foreach($piecesToAdd as ["kind" => $kind, "loc" => [$pieceX, $pieceY], "num" => $num]){
                for($i = 0; $i<$num; $i++){
                    $values[] = "($kind, $pieceX, $pieceY, TRUE)";
                }
            }
        }
        $args = implode(",", $values);
        self::DbQuery("INSERT INTO pieces (kind, x, y, ai) VALUES $args");
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
                ["type" => $preyType, "amt" => $numPrey] = array_pop($available_prey);

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
        $result['pieces'] = self::getObjectListFromDb("SELECT id, owner, kind, x, y, prey_metadata FROM pieces");
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

        $numEntries = (int)self::getUniqueValueFromDB("SELECT COUNT(*) FROM moonlight_board");
        return intval(($numEntries * 100) / FULL_DATES[self::getPlayersNumber()]);
    }


//////////////////////////////////////////////////////////////////////////////
//////////// Utility functions
////////////    

    function isImpassableTerrain($terrainType){
        return $terrainType === T_WATER || $terrainType === T_CHASM;
    }

    function getRegions(): array {
        return self::getObjectListFromDB('SELECT * FROM regions');
    }

    static function sql_hex_range($x1, $y1, $x2, $y2) {
        return "(ABS($x2 - $x1) + ABS($y2 - $y1) + ABS($x2 - $y2 - $x1 + $y1)) / 2";
    }

    static function sql_hex_in_range($x1, $y1, $x2, $y2, $range) {
        return "ABS($x2 - $x1) + ABS($y2 - $y1) + ABS($x2 - $y2 - $x1 + $y1) <= 2 * $range";
    }

    function getPlayers(): array {
        return self::getObjectListFromDb(<<<EOF
            SELECT player_id AS id, player_name AS name, player_color AS color, home_terrain AS terrain 
            FROM player NATURAL JOIN player_status 
            EOF);
    }

    function getLand(): array {
        return self::getObjectListFromDB('SELECT * FROM land');
    }

    function getPlayerTiles(int $player_id): array {
        $tiles = self::getObjectFromDB("SELECT `0`, `1`, `2`, `3`, `4` FROM `player_tiles` WHERE player_id=$player_id");
        $home_terrain = (int)self::getUniqueValueFromDB("SELECT `home_terrain` FROM `player_status` WHERE player_id=$player_id");
        $tiles[] = $home_terrain;
        return $tiles;
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
                    WHERE l.region_id=$regionId AND p.owner IS NOT NULL
                    GROUP BY p.owner
                    ORDER BY score, alphas
                    EOF;
        $playerPieces = self::getObjectListFromDB($query);
        $aiQuery = <<<EOF
                        "ai" as owner,
                        SELECT ((COUNT(CASE WHEN p.kind=$lair THEN 1 END) * 3) + COUNT(CASE WHEN p.kind=$den OR p.kind=$pack OR p.kind=$alpha THEN 1 END)) as score,
                        COUNT(CASE WHEN p.kind=$alpha THEN 1 END) as alphas
                        FROM land l
                        NATURAL LEFT JOIN pieces p
                        WHERE l.region_id=$regionId AND ai IS TRUE
                        GROUP BY owner
                      EOF;
        $aiPieces = self::getObjectFromDB($aiQuery);
        if(!is_null($aiPieces)){
            self::dump("AI Pieces", $aiPieces);
            $playerPieces[] = $aiPieces;
        }
        
        return $playerPieces;
    }

    function doHunt(): void {
        $preyType = P_PREY;
        $preyTokens = self::getObjectListFromDB("SELECT DISTINCT x, y, prey_metadata FROM pieces WHERE kind=$preyType");

        foreach($preyTokens as ["x" => $x, "y" => $y, "prey_metadata" => $preyData]){
            $args = [];
            foreach (HEX_DIRECTIONS as [$dx, $dy]) {
                $newX = $x + $dx;
                $newY = $y + $dy;
                $args[] = "(p.x=$newX AND p.y=$newY)";
            }

            $numPrey = (int)self::getUniqueValueFromDB("SELECT COUNT(*) FROM pieces WHERE x=$x AND y=$y AND kind=$preyType");

            $condition = implode(" OR ", $args);
            $numPlayers = self::getPlayersNumber();

            $currTurnPlayerId = self::getActivePlayerId();
            $currentTurnIndex = (int)self::getUniqueValueFromDB("SELECT player_no FROM player WHERE player_id=$currTurnPlayerId");
            $alphaKind = P_ALPHA;
            $packKind = P_PACK;
            $query = <<<EOF
                        SELECT p.owner as player_id, pl.player_name as name
                        FROM player_status s
                        LEFT JOIN pieces p ON s.player_id = p.owner
                        LEFT JOIN player pl ON s.player_id = pl.player_id
                        WHERE ($condition)
                        AND s.prey_data & $preyData = 0
                        AND p.kind IN ($packKind, $alphaKind)
                        GROUP BY p.owner
                        HAVING COUNT(DISTINCT p.x, p.y) >= 3
                        ORDER BY (pl.player_no + $numPlayers - $currentTurnIndex) % $numPlayers ASC
                        LIMIT $numPrey
                        EOF;

            $playerPresence = self::getObjectListFromDB($query);
            
            foreach($playerPresence as ["player_id" => $playerId, 'name' => $playerName]){
                self::DbQuery("UPDATE player_status SET turn_tokens=turn_tokens + 1, prey_data = prey_data | $preyData WHERE player_id=$playerId");
                self::DbQuery("DELETE FROM pieces WHERE x=$x AND y=$y AND kind=$preyKind LIMIT 1");
                self::notifyAllPlayers("hunted", clienttranslate('${player_name} has hunted a ${prey_type} and received a bonus turn token'),
                [
                    'player_id' => $playerId,
                    'player_name' => $playerName,
                    'prey_type' => PREY_NAMES[$preyData]
                ]);
                self::incStat(1, STAT_PLAYER_PREY_HUNTED, $playerId);
            }
        }
    }

    function regionScoring(): int {
        //Scoring
        $numPlayers = self::getPlayersNumber();
        $currentDate = (int)self::getUniqueValueFromDB("SELECT COUNT(*) FROM moonlight_board");
        $currentPhase = $this->getGameStateValue(G_MOON_PHASE);
        $phaseDate = PHASES[$currentPhase][$numPlayers];

        if($currentDate < $phaseDate){
            self::debug("$currentDate/$phaseDate");
            return $currentPhase;
        }

        self::debug("Region scoring");
        $this->incGameStateValue(G_MOON_PHASE, 1);

        // init player score states

        $player_ids =  array_keys($this->loadPlayersBasicInfos());  
        $playerStates = [];
        foreach($player_ids as $playerId){
            $playerStates[$playerId] = [
                "first_place" => 0,
                "second_place" => 0
            ];
        }
        if(count($player_ids) === 2){
            $playerStates["ai"] = [
                "first_place" => 0,
                "second_place" => 0
            ];
        }

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

                self::dump('presence', $presence);
                self::dump('winners', $winners);
                //multiple winners, no second place, and everyone gets half score
                if(count($winners) > 1){
                    foreach($winners as ["owner" => $winner]){
                        $playerStates[$winner]["second_place"]++;
                    }
                }
                //If one winner, maybe second place?
                
                else{
                    //Set first player points/score token
                    $firstPlace = $presence[0]['owner'];
                    $playerStates[$firstPlace]["first_place"]++;

                    //Only 2 people in region, second place is guaranteed
                    if(count($presence) === 2){
                        $secondPlace = $presence[1]['owner'];
                        $playerStates[$secondPlace]["second_place"]++;
                    }
                    //Otherwise, there can only be one player who wins second place
                    else if($presence[1]['points'] !== $presence[2]['points'] && $presence[1]['alphas'] !== $presence[2]['alphas']){
                        $secondPlace = $presence[1]['owner'];
                        $playerStates[$secondPlace]["second_place"]++;
                    }
                }
            }
            //Only 1 person with presence in region, so they win first place
            else{
                $winner = $presence[0]['owner'];
                $playerStates[$winner]["first_place"]++;
            }
            
        }

        if(isset($playerStates["ai"])){
            unset($playerStates["ai"]);
        }
        
        foreach($playerStates as $playerId => ["first_place" => $firstPlace, "second_place" => $secondPlace]){
            self::DbQuery("UPDATE player SET player_score=player_score + ($score * $firstPlace) + (($score/2) * $secondPlace)");
            if($firstPlace > 0){
                $args = implode(",", array_fill(0, $firstPlace, "($playerId, $currentPhase)"));
                self::DbQuery("INSERT INTO score_token (player_id, type) VALUES $args");
            }

            self::incStat($firstPlace, STAT_PLAYER_FIRST_PLACE, $playerId);
            self::incStat($secondPlace, STAT_PLAYER_SECOND_PLACE, $playerId);

            self::debug("Player ($playerId) has scored first place $firstPlace times, and second place $secondPlace times");
        }

        $firstRow = [clienttranslate('Player')];
        $firstPlace = [clienttranslate('First Place Score')];
        $secondPlace = [clienttranslate('Second Place Score')];
        $total = [clienttranslate('Total')];

        foreach($player_ids as $player_id){
            $playerName = self::getUniqueValueFromDB("SELECT player_name FROM player WHERE player_id=$player_id");
            $firstRow[] = [
                "str" => '${player_name}',
                'args' => ['player_name' => $playerName],
                'type' => 'header'
            ];

            $firstPlace[] = [
                "str" => '${points}',
                'args' => ['points' => $playerStates[$player_id]['first_place']],
                'type' => 'footer'
            ];

            $secondPlace[] = [
                "str" => '${points}',
                'args' => ['points' => $playerStates[$player_id]['second_place']],
                'type' => 'footer'
            ];

            $total[] = [
                "str" => '${points}',
                'args' => ['points' => array_sum($playerStates[$player_id])]
            ];
        }

        $this->notifyAllPlayers("tableWindow", '', [
            'id' => 'scoreTable',
            'title' => clienttranslate('Region Scoring'),
            'table' => [
                $firstRow,
                $firstPlace,
                $secondPlace,
                $total
            ],
            'closing' => clienttranslate('Okay')
        ]);
        return $currentPhase + 1;
    }

    function getPreyCount(int $playerId){
        $preyVal = (int)self::getUniqueValueFromDB("SELECT prey_data FROM player_status WHERE player_id=$playerId");
        $numPrey = 0;
        while($preyVal > 0){
            $numPrey += $preyVal & 1;
            $preyVal = $preyVal >> 1;
        }
        return $numPrey;
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
        if ((int)self::getUniqueValueFromDB($query) > 0 ) {
            throw new BgaUserException(_("This place is already occupied"));
        }

        $numPlayers = self::getPlayersNumber();
        
        $selectedRegion = (int)self::getUniqueValueFromDB("SELECT region_id FROM land where x=$x AND y=$y");
        if($numPlayers > 2){
            $chasmType = T_CHASM;
            $chasmRegion = (int)self::getUniqueValueFromDB("SELECT region_id FROM land WHERE terrain=$chasmType GROUP BY region_id");
        
            if($selectedRegion !== $chasmRegion){
                throw new BgaUserException(_("You may only draft into the chasm region!"));
            }

            $drafted = self::getObjectFromDB("SELECT x, y FROM pieces WHERE owner=$player_id GROUP BY x, y");
            if(!is_null($drafted)){
                $draftedY = (int)$drafted['y'];
                $centerY = (int)self::getUniqueValueFromDB("SELECT center_y FROM regions WHERE region_id=$chasmRegion");
                $hasDraftedTop = $draftedY - $centerY < 0;
                $isChoosingTop = $y - $centerY < 0;
                if($hasDraftedTop === $isChoosingTop){
                    throw new BgaUserException(_('Second draft location must be on the opposite side of the chasm!'));
                }
            }
        }
        else{
            $regionPhase = (int)self::getUniqueValueFromDB("SELECT moon_phase FROM regions WHERE region_id=$selectedRegion");
            if($regionPhase & M_CRESCENT > 0){
                throw new BgaUserException(_("You cannot draft to a region with a crescent moon!"));
            }
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
            'ids' => [$insertId, $insertId + 1],
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

        $playerBonusTerrainTokens = (int)self::getUniqueValueFromDb("SELECT terrain_tokens FROM player_status WHERE player_id = $playerId");
        if ($bonusTerrain > $playerBonusTerrainTokens) {
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
                $deployedDens = (int)self::getUniqueValueFromDB("SELECT deployed_pack_dens FROM player_status WHERE player_id=$playerId");
                $this->setGameStateValue(G_MOVES_REMAINING, PACK_SPREAD[$deployedDens]);
                break;
            case 'howl':
                $deployedWolves = (int)self::getUniqueValueFromDB("SELECT deployed_wolves FROM player_status WHERE player_id=$playerId");
                if($deployedWolves > count(WOLF_DEPLOYMENT)){
                    throw new BgaUserException(_('You have no wolves to deploy!'));
                }
                break;
            case 'den':
                $deployedDens = (int)self::getUniqueValueFromDB("SELECT (deployed_howl_dens + deployed_pack_dens + deployed_speed_dens) as deployed_dens FROM player_status WHERE player_id=$playerId");
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


        self::incStat($bonusTerrain, STAT_PLAYER_TERRAIN_TOKENS_SPENT);
        self::incStat($bonusTerrain, STAT_TERRAIN_TOKENS_SPENT);
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

        $deployedDens = (int)self::getUniqueValueFromDB("SELECT deployed_speed_dens FROM player_status WHERE player_id=$playerId");
        $max_distance = WOLF_SPEED[$deployedDens];
        if(count($path) > $max_distance){
            throw new BgaUserException(_('The selected tile is out of range'));
        }

        $pathCheck = function($x, $y) {
            $hex = self::getObjectFromDB("SELECT * FROM land WHERE x=$x AND y=$y");
            if($hex === NULL || $this->isImpassableTerrain($hex['terrain'])){
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
        self::incStat(1, STAT_PLAYER_WOLVES_MOVED, $playerId);
        if(count($potential_wolves) > 0){
            $pack_wolf = $potential_wolves[0];
            $this->setGameStateValue(G_DISPLACEMENT_WOLF, $pack_wolf['id']);
            $this->gamestate->nextState(TR_DISPLACE);
        }
        else{
            $this->gamestate->nextState(($newVal > 0) ? TR_MOVE : TR_END_MOVE);
        }  
    }

    function skip(): void {
        $this->checkAction('skip');
        $this->gamestate->nextState(TR_END_MOVE);
    }

    function getMaxDisplacement(int $x, int $y, int $playerId): int {
            $water = T_WATER;
            $chasm = T_CHASM;
            $query = <<<EOF
                SELECT {$this->sql_hex_range('l.x', 'l.y', $x, $y)} AS dist
                FROM land l NATURAL LEFT JOIN pieces p
                WHERE (l.terrain <> $water AND l.terrain <> $chasm)
                AND (SELECT COUNT(*) FROM pieces WHERE x = l.x AND y = l.y) < 2
                AND (p.kind IS NULL OR p.owner = $playerId)
                ORDER BY dist
                LIMIT 1
                EOF;
            return (int)self::getUniqueValueFromDB($query) ?? -1;
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
            $water = T_WATER;
            $chasm = T_CHASM;
            $query = <<<EOF
                SELECT l.*
                FROM land l
                LEFT JOIN pieces p ON l.x = p.x AND l.y = p.y
                WHERE (l.terrain <> $water AND l.terrain <> $chasm)
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
        self::incStat(1, STAT_PLAYER_LONE_WOLVES_CONVERTED, $playerId);

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
        $numDens = (int)self::getUniqueValueFromDB("SELECT $denCol FROM player_status WHERE player_id=$playerId");
        if($numDens >= DEN_COUNT){
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
        if ((int)self::getUniqueValueFromDB($query) === 0){
            throw new BgaUserException(_('Invalid hex selected!'));
        }

        $deployedDens = (int)self::getUniqueValueFromDB("SELECT $denCol FROM player_status WHERE player_id=$playerId");

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
        self::incStat(1, STAT_PLAYER_DENS_PLACED, $playerId);
        $this->gamestate->nextState(TR_POST_ACTION);

    }

    function placeLair(int $wolfId, ?int $path): void {
        self::checkAction('lair');

        $playerId = self::getActivePlayerId();
        $numLairs = (int)self::getUniqueValueFromDB("SELECT deployed_lairs FROM player_status WHERE player_id=$playerId");
        if($numLairs >= LAIR_COUNT){
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
        
        self::incStat(1, STAT_PLAyeR_DENS_UPGRADED, $playerId);
        $this->gamestate->nextState(TR_POST_ACTION);

    }

    function dominate(int $wolfId, int $targetId, int $denType): void {
        self::checkAction('dominate');
        $playerId = self::getActivePlayerId();
        $terrain = $this->getGameStateValue(G_SELECTED_TERRAIN);
        $wolf = self::getObjectFromDB("SELECT * FROM pieces WHERE id=$wolfId");
        $deployedDens = (int)self::getUniqueValueFromDB("SELECT deployed_howl_dens FROM player_status WHERE player_id=$playerId");
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
            $numDens = (int)self::getUniqueValueFromDB("SELECT $denName FROM player_status WHERE player_id=$playerId");
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
            $numWolves = (int)self::getUniqueValueFromDB("SELECT deployed_wolves FROM player_status WHERE player_id=$playerId");
            if($numWolves >= count(WOLF_DEPLOYMENT)){
                throw new BgaUserException(_('You have no more wolves you can deploy!'));
            }
            $wolfIndex = (int)self::getUniqueValueFromDB("SELECT deployed_wolves FROM player_status WHERE player_id=$playerId");
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

        // Set wolves on board stat for attacker & defender in case that was updated
        $stat = (int)$target['kind'] === P_DEN ? STAT_PLAYER_DENS_DOMINATED : STAT_PLAYER_WOLVES_DOMINATED;
        self::incStat(1, $stat, $playerId);
        
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
        $turnTokens = (int)self::getUniqueValueFromDB("SELECT turn_tokens FROM player_status WHERE player_id=$playerId");
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
        self::incStat(1, STAT_PLAYER_BONUS_ACTIONS_TAKEN, $playerId);
        self::incStat(1, STAT_BONUS_ACTIONS_TAKEN);
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
            "remainingTokens" => (int)self::getUniqueValueFromDB("SELECT turn_tokens FROM player_status WHERE player_id=$playerId")
        ];
    }
    

//////////////////////////////////////////////////////////////////////////////
//////////// Game state actions
////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.    */

    function stDraftResolution(): void {
        $wolvesDrafted = (int)self::getUniqueValueFromDB(
            "SELECT COUNT(*)/2 FROM pieces WHERE owner IS NOT NULL");
        $numPlayers = self::getPlayersNumber();
        $draftCompleted = $wolvesDrafted >= (2 * $numPlayers);
        

        $type = gettype($wolvesDrafted);
        self::debug("TESTING: $type");
        self::debug("Wolves drafted: $wolvesDrafted, numPlayers: $numPlayers");
        if ($wolvesDrafted % $numPlayers !== 0 && !$draftCompleted) {
            if($wolvesDrafted > $numPlayers){
                $this->activePrevPlayer();
            }
            else{
                $this->activeNextPlayer();
            }
        }
        if($draftCompleted){
            self::DbQuery("INSERT INTO turn_log VALUES ()");
        }
        $this->gamestate->nextState($draftCompleted ? TR_DRAFT_END : TR_DRAFT_CONTINUE);
    }

    function stPostAction(): void {
        $playerId = self::getActivePlayerId();
        $remainingActions = $this->incGameStateValue(G_ACTIONS_REMAINING, -1);
        $this->doHunt();
        $this->gamestate->nextState($remainingActions == 0 ? TR_CONFIRM_END : TR_SELECT_ACTION);
        
    }

    function stNextTurn(): void {

        $currentPlayer = self::getActivePlayerId();
        self::incStat(1, STAT_PLAYER_TURNS_PLAYED, $currentPlayer);
        self::incStat(1, STAT_TURNS_TAKEN);
        $currentPhase = $this->regionScoring();
        self::DbQuery("INSERT INTO turn_log VALUES ()");
        //Determine if the game should end
        if($currentPhase > 2){
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

    function ___debugAddTerrain(): void {
        $playerId = self::getActivePlayerId();
        $tokens = 100;
        self::DbQuery("UPDATE player_status SET terrain_tokens = $tokens WHERE player_id = $playerId");
        self::notifyAllPlayers('update', '${player_name} gained bonus terrain tokens by cheating',
            [
                'player_name' => self::getActivePlayerName(),
                'newAttributes' => [
                    'playerId' => $playerId,
                    'terrain_tokens' => $tokens
                ]
            ]);
    }

    function ___debugRegionScoring(int $phase){
        if($phase > 2 || $phase < 0){
            throw new BgaUserException(_("This aint it"));
        }

        $numPlayers = $this->getPlayersNumber();

        $this->setGameStateValue(G_MOON_PHASE, $phase);
        self::DbQuery("DELETE FROM moonlight_board");
        $entries = [];
        $loneWolfKind = P_LONE;
        for($i = 0; $i<PHASES[$phase][$numPlayers]; $i++){
            $entries[] = "($loneWolfKind)";
        }
        $args = implode(",", $entries);

        $query = "INSERT INTO moonlight_board (kind) VALUES $args";

        self::debug("QUERY IS $query");

        self::DbQuery($query);

        $this->regionScoring();

    }
}

