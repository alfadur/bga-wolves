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

require_once(APP_GAMEMODULE_PATH . 'module/table/table.game.php');
require_once('modules/constants.inc.php');

class TheWolves extends Table
{
    function __construct()
    {
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
            G_GIVEN_TIME => 15,
            G_MOON_PHASE => 16,
            G_ACTIONS_TAKEN => 17,
            G_DRAFT_PROGRESS => 18,
        ]);
    }

    protected function getGameName(): string
    {
        // Used for translations and stuff. Please do not modify.
        return 'thewolves';
    }

    /*
        setupNewGame:
        
        This method is called only once, when a new game is launched.
        In this method, you must setup the game according to the game rules, so that
        the game is ready to be played.
    */
    protected function setupNewGame($players, $options = []): void
    {
        $gameInfo = self::getGameinfos();
        $colorTerrains = array_map(null, $gameInfo['player_colors'], range(0, TILE_TERRAIN_TYPES - 1));
        shuffle($colorTerrains);

        // Create players
        $playerValues = [];
        $statusValues = [];

        foreach ($players as $playerId => $player) {
            [$color, $terrain] = array_shift($colorTerrains);

            $name = addslashes($player['player_name']);
            $avatar = addslashes($player['player_avatar']);
            $playerValues[] = "('$playerId','$color','$player[player_canal]','$name','$avatar')";

            $statusValues[] = "($playerId, $terrain, '0', '0', '0', '0', '0')";
        }

        $args = implode(',', $playerValues);
        $query = "INSERT INTO player (player_id, player_color, player_canal, player_name, player_avatar) VALUES $args";
        self::DbQuery($query);

        $args = implode(',', $statusValues);
        $query = "INSERT INTO player_status (player_id, home_terrain, tile_0, tile_1, tile_2, tile_3, tile_4) VALUES $args";
        self::DbQuery($query);

        self::reloadPlayersBasicInfos();

        /************ Start the game initialization *****/

        self::setGameStateInitialValue(G_SELECTED_TERRAIN, -1);
        self::setGameStateInitialValue(G_ACTIONS_REMAINING, 2);
        self::setGameStateInitialValue(G_MOVES_REMAINING, -1);
        self::setGameStateInitialValue(G_MOVED_WOLVES, 0);
        self::setGameStateInitialValue(G_DISPLACEMENT_WOLF, -1);
        self::setGameStateInitialValue(G_MOON_PHASE, 0);
        self::setGameStateInitialValue(G_GIVEN_TIME, 0);
        self::setGameStateInitialValue(G_ACTIONS_TAKEN, 0);
        self::setGameStateInitialValue(G_DRAFT_PROGRESS, 0);

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
        foreach ($playerStats as $stat) {
            self::initStat("player", $stat, 0);
        }

        $tableStats = [
            STAT_TURNS_TAKEN,
            STAT_BONUS_ACTIONS_TAKEN,
            STAT_TERRAIN_TOKENS_SPENT
        ];
        foreach ($tableStats as $stat) {
            self::initStat("table", $stat, 0);
        }

        $this->generateLand(count($players));
        $this->generatePieces($players);

        // Activate first player (which is in general a good idea :) )
        $this->activeNextPlayer();

        /************ End of the game initialization *****/
    }

    protected function generateLand(int $player_count): void
    {
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

        if ($player_count === 2) {
            $this->generateAIPieces();
            $currentPlayer = $this->getNextPlayerTable()[0];
            self::DbQuery("UPDATE player_status SET action_tokens=action_tokens+1 WHERE player_id <> $currentPlayer");
        }
    }

    function generateAIPieces()
    {
        $regions = self::getObjectListFromDB("SELECT region_id, moon_phase, center_x, center_y FROM regions");
        $values = [];
        foreach ($regions as ['moon_phase' => $moonPhase, 'center_x' => $x, 'center_y' => $y]) {
            $moonPhase = (int)$moonPhase;

            $x = (int)$x;
            $y = (int)$y;
            $topRight = [$x + HD_TOP_RIGHT[0], $y + HD_TOP_RIGHT[1]];
            $bottomRight = [$x + HD_BOTTOM_RIGHT[0], $y + HD_BOTTOM_RIGHT[1]];
            $piecesToAdd = [];
            switch ($moonPhase) {
                case M_CRESCENT:
                    $piecesToAdd[] = ['kind' => P_PACK, 'loc' => $topRight, 'num' => 2];
                    break;
                case M_CRES_HALF:
                    $piecesToAdd[] = ['kind' => P_LAIR, 'loc' => $topRight, 'num' => 1];
                    break;
                case M_FULL:
                    $piecesToAdd[] = ['kind' => P_ALPHA, 'loc' => $topRight, 'num' => 1];
                    $piecesToAdd[] = ['kind' => P_LAIR, 'loc' => $topRight, 'num' => 1];
                    break;
                case M_QUARTER_FULL:
                    $piecesToAdd[] = ['kind' => P_LAIR, 'loc' => $topRight, 'num' => 1];
                    $piecesToAdd[] = ['kind' => P_ALPHA, 'loc' => $bottomRight, 'num' => 2];
                    break;
            }
            foreach ($piecesToAdd as ['kind' => $kind, 'loc' => [$pieceX, $pieceY], 'num' => $num]) {
                for ($i = 0; $i < $num; $i++) {
                    $values[] = "($kind, $pieceX, $pieceY)";
                }
            }
        }
        $args = implode(',', $values);
        self::DbQuery("INSERT INTO pieces (kind, x, y) VALUES $args");
    }

    protected function generatePieces(array $players): void
    {
        $values = [];
        $kind = P_LONE;
        $preyKind = P_PREY;
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
                for ($i = 0; $i < $numPrey; $i++) {
                    $values[] = "($x, $y, $preyKind, $preyType)";
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
    protected function getAllDatas(): array
    {
        $result = [];

        // Get information about players
        $result['players'] = self::getCollectionFromDb(
            'SELECT player_id AS id, player_score AS score, player_color AS color FROM player'
        );
        $result['status'] = self::getObjectListFromDb('SELECT * FROM player_status');
        $result['regions'] = self::getObjectListFromDb(
            'SELECT region_id AS id, center_x AS x, center_y AS y, moon_phase AS phase FROM regions'
        );
        $result['pieces'] = self::getObjectListFromDb("SELECT id, owner, kind, x, y, prey_metadata FROM pieces");
        $result['calendar'] = self::getObjectListFromDb("SELECT player_id AS owner, kind FROM moonlight_board");

        $result['tokens'] = self::getObjectListFromDb("SELECT player_id AS playerId, type FROM score_token");

        $currentPhase = $this->getGameStateValue(G_MOON_PHASE);
        if ($currentPhase < count(PHASES)) {
            $result['nextScoring'] =
                PHASES[$currentPhase][$this->getPlayersNumber()];
        }

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
    function getGameProgression(): int
    {
        $numEntries = (int)self::getUniqueValueFromDB("SELECT COUNT(*) FROM moonlight_board");
        return intval(($numEntries * 100) / FULL_DATES[self::getPlayersNumber()]);
    }


    //////////////////////////////////////////////////////////////////////////////
    //////////// Utility functions
    ////////////    

    function isImpassableTerrain($terrainType)
    {
        return $terrainType === T_WATER || $terrainType === T_CHASM;
    }

    function getRegions(): array
    {
        return self::getObjectListFromDB('SELECT * FROM regions');
    }

    static function sql_hex_in_range($x1, $y1, $x2, $y2, $range): string
    {
        return "ABS($x2 - $x1) + ABS($y2 - $y1) + ABS($x2 - $y2 - $x1 + $y1) <= 2 * $range";
    }

    function getPlayers(): array
    {
        return self::getObjectListFromDb(<<<EOF
            SELECT player_id AS id, player_name AS name, player_color AS color, home_terrain AS terrain 
            FROM player NATURAL JOIN player_status 
            EOF);
    }

    static function getLand(): array
    {
        return self::getObjectListFromDB('SELECT * FROM land');
    }

    static function checkPath(
        array $start,
        array $steps,
        ?array $impassableTerrains = null,
        ?string $sharedPlayerId = null
    ): array {
        $terrainCheck = $impassableTerrains === null ? '' :
            ' AND terrain NOT IN (' . implode(',', $impassableTerrains) . ')';
        $stepChecks = [];
        foreach ($steps as $i => $step) {
            [$dx, $dy] = HEX_DIRECTIONS[$step];
            $start[0] += $dx;
            $start[1] += $dy;
            if ($i < count($steps) - 1) {
                $sharingCheck = $sharedPlayerId === null ? '' : <<<EOF
                     AND EXISTS (SELECT * FROM pieces 
                        WHERE x = $start[0] AND y = $start[1]
                        GROUP BY owner
                        HAVING owner IS NULL 
                            OR owner <> $sharedPlayerId
                            OR COUNT(*) > 1)
                    EOF;
                $stepChecks[] = "(x = $start[0] AND y = $start[1]$sharingCheck)";
            }
        }

        if (count($steps) > 1) {
            $stepChecks = implode(" OR ", $stepChecks);
            $validSteps = self::getUniqueValueFromDB(
                "SELECT COUNT(*) FROM land WHERE ($stepChecks)$terrainCheck"
            );
            if ((int)$validSteps !== count($steps) - 1) {
                throw new BgaUserException("Invalid action");
            }
        }

        return $start;
    }

    static function checkDestination(int $targetX, int $targetY, $playerId, array $sharedKinds = [], ?int $terrain = null)
    {
        $sharedKinds[] = 'NULL';
        $chasm = T_CHASM;
        $water = T_WATER;
        $terrainCheck = $terrain === null ?
            " AND terrain NOT IN ($chasm, $water)" :
            " AND terrain = $terrain";
        $kinds = implode(',', $sharedKinds);
        $query = <<<EOF
            SELECT COUNT(*) FROM land NATURAL LEFT JOIN pieces
            WHERE x = $targetX AND y = $targetY$terrainCheck
            HAVING COUNT(*) < 2 AND COUNT(
                CASE WHEN owner <> $playerId AND kind NOT IN ($kinds) 
                    THEN 1 END) = 0
            EOF;

        if ((int)self::getUniqueValueFromDB($query) === 0) {
            throw new BgaUserException('Invalid action');
        }
    }

    function addMovedWolf(int $wolfId)
    {
        $moved_wolves = $this->getGameStateValue(G_MOVED_WOLVES);
        $moved_wolves = $moved_wolves << 8;
        $moved_wolves |= ($wolfId & 0xff);
        $this->logSetGamestateValue(G_MOVED_WOLVES, $moved_wolves);
    }

    function getMovedWolves(): array
    {
        $moved_wolves = $this->getGameStateValue(G_MOVED_WOLVES);
        $wolf_ids = [];
        for ($i = 0; ($i / 8) < 4; $i += 8) {
            $wolf_id = ($moved_wolves & (0xff << $i)) >> $i;
            if ($wolf_id > 0) {
                $wolf_ids[] = $wolf_id;
            }
        }

        return $wolf_ids;
    }

    function flipTiles(string $playerId, array $tileIndices): int
    {
        $tiles = self::getObjectFromDB(<<<EOF
            SELECT tile_0, tile_1, tile_2, tile_3, tile_4, home_terrain
            FROM player_status WHERE player_id=$playerId
            EOF);

        $terrain = -1;
        $sets = [];

        foreach ($tileIndices as $tileIndex) {
            if ($tileIndex < TILE_TERRAIN_TYPES) {
                $isFlipped = &$tiles["tile_$tileIndex"];
                $nextTerrain = ($tileIndex + $isFlipped) % TILE_TERRAIN_TYPES;
                $sets[] = "tile_$tileIndex = 1 - tile_$tileIndex";
                $isFlipped = 1 - (int)$isFlipped;
            } else {
                $nextTerrain = (int)$tiles['home_terrain'];
            }

            if ($terrain >= 0 && $nextTerrain !== $terrain) {
                throw new BgaUserException('All tiles must have identical terrain');
            }
            $terrain = $nextTerrain;
        }

        if (count($sets) > 0) {
            $update = implode(', ', $sets);
            $this->logDBUpdate('player_status', $update, "player_id=$playerId", $update);
        }

        return $terrain;
    }

    function getDenAwards(int $denType, int $deployedDens): ?int
    {
        switch (DEN_COLS[$denType]) {
            case 'howl':
                return HOWL_RANGE_AWARDS[self::getPlayersNumber()][$deployedDens];
            case 'pack':
                return PACK_SPREAD_AWARDS[self::getPlayersNumber()][$deployedDens];
            case 'speed':
                return WOLF_SPEED_AWARDS[self::getPlayersNumber()][$deployedDens];
        }
        return null;
    }

    static function getRegionPresence(int $regionId): array
    {
        $lair = P_LAIR;
        $den = P_DEN;
        $alpha = P_ALPHA;
        $pack = P_PACK;
        $pieces = self::getObjectListFromDB(<<<EOF
            SELECT owner, 
                SUM(CASE kind WHEN $lair THEN 3 ELSE 1 END) AS score,
                COUNT(CASE kind WHEN $alpha THEN 1 END) AS alphas
            FROM pieces NATURAL JOIN land  
            WHERE region_id = $regionId AND kind IN ($alpha, $pack, $den, $lair)
            GROUP BY owner
            ORDER BY score DESC, alphas DESC
            EOF);
        foreach ($pieces as &$piece) {
            $piece['owner'] ??= 'ai';
        }
        return $pieces;
    }

    function doHunt(): void
    {
        $playerId = self::getActivePlayerId();
        $preyType = P_PREY;
        $preyTokens = self::getObjectListFromDB("SELECT DISTINCT id, x, y, prey_metadata FROM pieces WHERE kind=$preyType");

        foreach ($preyTokens as ['id' => $id, 'x' => $x, 'y' => $y, 'prey_metadata' => $preyData]) {
            $alphaKind = P_ALPHA;
            $packKind = P_PACK;
            $query = <<<EOF
                SELECT COUNT(DISTINCT x, y) AS count, BIT_COUNT(prey_data) AS hunted
                FROM pieces JOIN player_status ON owner = player_id
                WHERE x BETWEEN $x - 1 AND $x + 1
                    AND y BETWEEN $y - 1 AND $y + 1 
                    AND {$this->sql_hex_in_range('x', 'y',$x,$y, 1)}
                    AND player_id = $playerId
                    AND prey_data & $preyData = 0
                    AND kind IN ($packKind, $alphaKind)
                EOF;

            $data = self::getObjectFromDB($query);
            if ((int)$data['count'] < 3) {
                continue;
            }

            $scoreIndex = (int)$data['hunted'];
            $playerCount = self::getPlayersNumber();
            $score = $playerCount === 2 ?
                HUNT_SCORE_2P[$scoreIndex] : HUNT_SCORE[$scoreIndex];

            $this->logDBUpdate('player', "player_score = player_score + $score", "player_id = $playerId", "player_score = player_score - $score");

            $args = [
                'player_name' => self::getActivePlayerName(),
                'huntUpdate' => [
                    'id' => $id,
                    'hunter' => $playerId,
                    'x' => $x,
                    'y' => $y,
                    'preyData' => $preyData
                ],
                'scoreUpdate' => [
                    'playerId' => $playerId,
                    'increment' => $score
                ]
            ];

            if ($playerCount > 2) {
                $this->logDBUpdate(
                    "player_status",
                    "action_tokens = action_tokens + 1, prey_data = prey_data | $preyData",
                    "player_id = $playerId",
                    "action_tokens = action_tokens - 1, prey_data = prey_data ^ $preyData"
                );
                $args = array_merge($args, [
                    'attributesUpdate' => [
                        'playerId' => $playerId,
                        'actionTokens' => 1
                    ],
                ]);
            } else {
                $this->logDBUpdate(
                    "player_status",
                    "prey_data = prey_data | $preyData",
                    "player_id = $playerId",
                    "prey_data = prey_data ^ $preyData"
                );
            }

            $this->logNotification(clienttranslate('${player_name} returns the prey back'), $args);
            $this->logDBDelete("pieces", "id = $id");

            $args['preyIcon'] = $preyData;
            self::notifyAllPlayers(
                'update',
                clienttranslate('${player_name} hunts down a ${preyIcon}'),
                $args
            );
            $this->logIncStat(STAT_PLAYER_PREY_HUNTED, 1, $playerId);
        }
    }

    function regionScoring(): int
    {
        //Scoring
        $numPlayers = self::getPlayersNumber();
        $currentDate = (int)self::getUniqueValueFromDB("SELECT COUNT(*) FROM moonlight_board");
        $currentPhase = $this->getGameStateValue(G_MOON_PHASE);
        $phaseDate = PHASES[$currentPhase][$numPlayers];

        if ($currentDate < $phaseDate) {
            return $currentPhase;
        }

        $this->incGameStateValue(G_MOON_PHASE, 1);

        // init player score states

        $playerIds = array_keys($this->loadPlayersBasicInfos());
        $playerStates = [];
        foreach ($playerIds as $playerId) {
            $playerStates[$playerId] = [
                'first_place' => 0,
                'second_place' => 0
            ];
        }
        if (count($playerIds) === 2) {
            $playerStates['ai'] = [
                'first_place' => 0,
                'second_place' => 0
            ];
        }

        //Determine condition for region
        $phaseBitMask = [M_CRESCENT, M_QUARTER, M_FULL][$currentPhase];

        $scoringRegions = self::getObjectListFromDB("SELECT * FROM regions WHERE moon_phase & $phaseBitMask > 0");
        $score = ($currentPhase * 2) + 4;

        foreach ($scoringRegions as &$region) {
            $presence = self::getRegionPresence($region['region_id']);

            //If no one is in the region, no one scores
            if (count($presence) == 0) {
                continue;
            }

            //Determine who won

            //At least 2 people in region
            if (count($presence) > 1) {
                //Determine how many players won first place
                $firstWinner = $presence[0];
                $winners = array_filter($presence, fn ($thisPlayer) => $firstWinner['score'] === $thisPlayer['score'] && $firstWinner['alphas'] === $thisPlayer['alphas']);

                //multiple winners, no second place, and everyone gets half score
                if (count($winners) > 1) {
                    foreach ($winners as ['owner' => $winner]) {
                        $playerStates[$winner]['second_place']++;
                    }
                } else {
                    $region['winner'] = $winners[0]['owner'];
                    //If one winner, maybe second place?
                    //Set first player points/score token
                    $firstPlace = $presence[0]['owner'];
                    $playerStates[$firstPlace]['first_place']++;

                    //Only 2 people in region, second place is guaranteed
                    if (count($presence) === 2) {
                        $secondPlace = $presence[1]['owner'];
                        $playerStates[$secondPlace]["second_place"]++;
                    } else if ($presence[1]['score'] !== $presence[2]['score'] || $presence[1]['alphas'] !== $presence[2]['alphas']) {
                        //Otherwise, there can only be one player who wins second place
                        $secondPlace = $presence[1]['owner'];
                        $playerStates[$secondPlace]["second_place"]++;
                    }
                }
            } else {
                //Only 1 person with presence in region, so they win first place
                $winner = $presence[0]['owner'];
                $region['winner'] = $winner;
                $playerStates[$winner]['first_place']++;
            }
        }

        self::DbQuery("UPDATE regions SET moon_phase = moon_phase ^ $phaseBitMask WHERE moon_phase & $phaseBitMask <> 0");

        if (isset($playerStates['ai'])) {
            unset($playerStates['ai']);
        }

        $notificationArgs = [
            'awards' => array_map(
                fn ($region) => [
                    'regionId' => $region['region_id'],
                    'winner' => $region['winner'] ?? null
                ],
                $scoringRegions
            )
        ];

        $nextPhase = $currentPhase + 1;
        if ($nextPhase < count(PHASES)) {
            $notificationArgs['nextPhase'] = $nextPhase;
            $notificationArgs['nextScoring'] =
                PHASES[$nextPhase][$this->getPlayersNumber()];
        }

        $scoreIncrements = [];

        foreach ($playerStates as $playerId => ["first_place" => $firstPlace, "second_place" => $secondPlace]) {
            $scoreIncrements[$playerId] = $score * $firstPlace + $score / 2 * $secondPlace;
            self::DbQuery("UPDATE player SET player_score = player_score + $scoreIncrements[$playerId] WHERE player_id = $playerId");
            if ($firstPlace > 0) {
                $args = implode(',', array_fill(0, $firstPlace, "($playerId, $currentPhase)"));
                self::DbQuery("INSERT INTO score_token (player_id, type) VALUES $args");
            }

            self::incStat($firstPlace, STAT_PLAYER_FIRST_PLACE, $playerId);
            self::incStat($secondPlace, STAT_PLAYER_SECOND_PLACE, $playerId);
        }

        $notificationArgs['scores'] = $scoreIncrements;
        $this->notifyAllPlayers('scoring', clienttranslate('Regions are scored'), $notificationArgs);

        $firstRow = [clienttranslate('Player')];
        $firstPlace = [clienttranslate('First Place Score')];
        $secondPlace = [clienttranslate('Second Place Score')];
        $total = [clienttranslate('Total')];

        foreach ($playerIds as $playerId) {
            $playerName = self::getPlayerNameById($playerId);
            $firstRow[] = [
                'str' => '${player_name}',
                'args' => ['player_name' => $playerName],
                'type' => 'header'
            ];

            $firstPlace[] = [
                'str' => '${points}',
                'args' => ['points' => $playerStates[$playerId]['first_place'] * $score],
                'type' => 'footer'
            ];

            $secondPlace[] = [
                'str' => '${points}',
                'args' => ['points' => $playerStates[$playerId]['second_place'] * $score / 2],
                'type' => 'footer'
            ];

            $total[] = [
                'str' => '${points}',
                'args' => ['points' => $scoreIncrements[$playerId]]
            ];
        }

        $this->notifyAllPlayers('tableWindow', '', [
            'id' => 'scoreTable',
            'title' => clienttranslate('Region Scoring'),
            'table' => [
                $firstRow,
                $firstPlace,
                $secondPlace,
                $total
            ],
            'closing' => clienttranslate('Close')
        ]);
        return $nextPhase;
    }

    function getPreyCount(string $playerId)
    {
        $preyVal = (int)self::getUniqueValueFromDB("SELECT prey_data FROM player_status WHERE player_id=$playerId");
        $numPrey = 0;
        while ($preyVal > 0) {
            $numPrey += $preyVal & 1;
            $preyVal = $preyVal >> 1;
        }
        return $numPrey;
    }

    function giveDenAward(int $denType, int $deployedDens, string $playerId): array
    {
        $denCol = 'deployed_' . DEN_COLS[$denType] . '_dens';
        $award = $this->getDenAwards($denType, $deployedDens);
        $rewardString = "";
        $reverseString = "";
        $numPlayers = $this->getPlayersNumber();
        $numPoints = ($numPlayers === 2 ? DEN_SCORE_2P : DEN_SCORE)[$deployedDens];
        $changes = [
            'playerId' => $playerId,
            $denCol => 1
        ];

        if ($award === AW_TERRAIN) {
            $changes['terrainTokens'] = 1;
            $rewardString = ", terrain_tokens=terrain_tokens + 1";
            $reverseString = ", terrain_tokens=terrain_tokens - 1";
        } else if ($award === AW_ACTION) {
            $changes['actionTokens'] = 1;
            $rewardString = ", action_tokens=action_tokens + 1";
            $reverseString = ", action_tokens=action_tokens - 1";
        }

        $this->logDBUpdate("player_status", "$denCol = $denCol + 1$rewardString", "player_id = $playerId", "$denCol = $denCol - 1$reverseString");
        $this->logDBUpdate('player', "player_score = player_score + $numPoints", "player_id = $playerId", "player_score = player_score - $numPoints");
        return [
            'attributesUpdate' => $changes,
            'scoreUpdate' => [
                'playerId' => $playerId,
                'increment' => $numPoints
            ]
        ];
    }

    function playerBonusTime() {
        $bonuses_given = $this->getGameStateValue(G_GIVEN_TIME);
        $actions_taken = $this->getGameStateValue(G_ACTIONS_TAKEN);

        if(($bonuses_given & (1 << $actions_taken)) == 0) {
            $this->setGameStateValue(G_GIVEN_TIME, $bonuses_given | (1 << $actions_taken));
            $this->giveExtraTime($this->getActivePlayerId());
        }
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Player actions
    //////////// 

    /*
        Each time a player is doing some game action, one of the methods below is called.
        (note: each method below must match an input method in thewolves.action.php)
    */

    function draftPlace(int $x, int $y): void
    {
        self::checkAction('draftPlace');
        $playerId = self::getActivePlayerId();

        $numPlayers = self::getPlayersNumber();

        $waterType = T_WATER;
        $chasmType = T_CHASM;

        $selectedRegion = self::getUniqueValueFromDB(<<<EOF
            SELECT region_id 
            FROM land NATURAL LEFT JOIN pieces 
            WHERE x=$x AND y=$y 
              AND terrain NOT IN ($waterType, $chasmType)
              AND kind IS NULL
            EOF);
        if ($selectedRegion === null) {
            throw new BgaUserException('Invalid draft location');
        }
        $selectedRegion = (int)$selectedRegion;
        if ($numPlayers > 2) {
            $chasmRegion = (int)self::getUniqueValueFromDB("SELECT region_id FROM land WHERE terrain=$chasmType GROUP BY region_id");

            if ($selectedRegion !== $chasmRegion) {
                throw new BgaUserException('You may only draft into the chasm region!');
            }

            $drafted = self::getObjectFromDB("SELECT x, y FROM pieces WHERE owner=$playerId GROUP BY x, y");
            if (!is_null($drafted)) {
                $draftedY = (int)$drafted['y'];
                $centerY = (int)self::getUniqueValueFromDB("SELECT center_y FROM regions WHERE region_id=$chasmRegion");
                $hasDraftedTop = $draftedY - $centerY < 0;
                $isChoosingTop = $y - $centerY < 0;
                if ($hasDraftedTop === $isChoosingTop) {
                    throw new BgaUserException('Second draft location must be on the opposite side of the chasm!');
                }
            }
        } else {
            $regionPhase = (int)self::getUniqueValueFromDB("SELECT moon_phase FROM regions WHERE region_id=$selectedRegion");
            if ($regionPhase & M_CRESCENT > 0) {
                throw new BgaUserException('You cannot draft to a region with a crescent moon!');
            }
        }


        $kinds = [P_ALPHA, P_PACK];
        $values = array_map(fn ($piece) => "($x, $y, $playerId, $piece)", $kinds);
        $args = implode(", ", $values);
        $query = "INSERT INTO pieces (x, y, owner, kind) VALUES $args";
        self::DbQuery($query);

        $insertId = self::DbGetLastId();
        $this->notifyAllPlayers('draft', clienttranslate('${player_name} places ${pieceIcon0}${pieceIcon1}'), [
            'player_name' => self::getActivePlayerName(),
            'playerId' => $playerId,
            'x' => $x,
            'y' => $y,
            'ids' => [$insertId, $insertId + 1],
            'kinds' => $kinds,
            'pieceIcon0' => "$playerId,$kinds[0]",
            'pieceIcon1' => "$playerId,$kinds[1]",
            'preserve' => ['pieceIcon0', 'pieceIcon1']
        ]);

        $this->gamestate->nextState('draftPlace');
    }

    function selectAction(int $action, array $tiles, int $bonusTerrain, ?int $forceTerrain): void
    {
        self::checkAction('selectAction');
        if (!array_key_exists($action, ACTION_COSTS)) {
            throw new BgaUserException('Invalid action selected');
        }

        $playerId = self::getActivePlayerId();

        $playerBonusTerrainTokens = (int)self::getUniqueValueFromDb("SELECT terrain_tokens FROM player_status WHERE player_id = $playerId");
        if ($bonusTerrain > $playerBonusTerrainTokens) {
            throw new BgaUserException('Not enough bonus terrain tokens');
        }

        $cost = ACTION_COSTS[$action];
        if (count($tiles) + $bonusTerrain != $cost) {
            throw new BgaUserException('Invalid tile count');
        }

        if ($forceTerrain && count($tiles) > 0) {
            throw new BgaUserException('Cannot force terrain when flipping tiles');
        }

        $terrain = $forceTerrain ?? $this->flipTiles($playerId, $tiles);

        switch ($action) {
            case A_MOVE:
                $this->logSetGameStateValue(G_MOVED_WOLVES, 0);
                $deployedDens = (int)self::getUniqueValueFromDB("SELECT deployed_pack_dens FROM player_status WHERE player_id=$playerId");
                $this->logsetGameStateValue(G_MOVES_REMAINING, PACK_SPREAD[$deployedDens]);
                break;
            case A_HOWL:
                $deployedWolves = (int)self::getUniqueValueFromDB("SELECT deployed_wolves FROM player_status WHERE player_id=$playerId");
                if ($deployedWolves > count(WOLF_DEPLOYMENT)) {
                    throw new BgaUserException('You have no wolves to deploy!');
                }
                break;
            case A_DEN:
                $deployedDens = (int)self::getUniqueValueFromDB("SELECT (deployed_howl_dens + deployed_pack_dens + deployed_speed_dens) as deployed_dens FROM player_status WHERE player_id=$playerId");
                if ($deployedDens >= count(HOWL_RANGE) + count(PACK_SPREAD) + count(WOLF_SPEED)) {
                    throw new BgaUserException('You have no dens to deploy!');
                }
                break;
            default:
                break;
        }

        if ($bonusTerrain) {
            $this->logDBUpdate("player_status", "terrain_tokens = terrain_tokens - $bonusTerrain", "player_id = $playerId", "terrain_tokens = terrain_tokens + $bonusTerrain");
            $this->logIncStat(STAT_PLAYER_TERRAIN_TOKENS_SPENT, $bonusTerrain, $playerId);
            $this->logIncStat(STAT_TERRAIN_TOKENS_SPENT, $bonusTerrain);
        }

        $this->logSetGameStateValue(G_SELECTED_TERRAIN, $terrain);

        $args = [
            'player_name' => self::getActivePlayerName(),
            'tilesUpdate' => [
                'playerId' => $playerId,
                'flippedTiles' => $tiles,
            ]
        ];

        if ($bonusTerrain > 0) {
            $args['attributesUpdate'] = [
                'playerId' => $playerId,
                'terrainTokens' => -$bonusTerrain
            ];
        }

        $this->logNotification(clienttranslate('${player_name} flips the tiles back'), $args);

        $args = array_merge($args, [
            'tilesCount' => count($tiles),
            'actionName' => $this->actionNames[$action],
            'i18n' => ['actionName'],
            'preserve' => ['actionName']
        ]);

        $tileIcons = implode(',', [$terrain, ...$tiles]);
        $bonusIcons = "terrain,$bonusTerrain";

        if ($bonusTerrain > 0 && count($tiles) > 0) {
            $args['tokenIcons'] = $bonusIcons;
            $args['tileIcons'] = $tileIcons;
            $args['preserve'][] = 'tokensIcons';
            $args['preserve'][] = 'tileIcons';
            $this->notifyAllPlayers('update', clienttranslate('${player_name} flips ${tileIcons} and spends ${tokenIcons} to perform the "${actionName}" action'), $args);
        } else if ($bonusTerrain > 0) {
            $args['tokenIcons'] = $bonusIcons;
            $args['terrainIcon'] = $forceTerrain;
            $args['preserve'][] = 'tokensIcons';
            $args['preserve'][] = 'terrainIcon';
            $this->notifyAllPlayers('update', clienttranslate('${player_name} spends ${tokenIcons} to perform the "${actionName}" action at ${terrainIcon}'), $args);
        } else {
            $args['tileIcons'] = $tileIcons;
            $args['preserve'][] = 'tileIcons';
            $this->notifyAllPlayers('update', clienttranslate('${player_name} flips ${tileIcons} to perform the "${actionName}" action'), $args);
        }

        $transition = ['move', 'howl', 'den', 'lair', 'dominate'][$action];
        $this->gamestate->nextState("${transition}Select");
    }

    function move(int $wolfId, array $steps): void
    {
        self::checkAction('move');

        $movedWolves = $this->getMovedWolves();

        if (in_array($wolfId, $movedWolves)) {
            throw new BgaUserException('This wolf has already been moved this turn!');
        }

        $playerId = self::getActivePlayerId();
        $terrain = $this->getGameStateValue(G_SELECTED_TERRAIN);
        $wolf = self::getObjectFromDB(
            "SELECT * FROM pieces WHERE id=$wolfId"
        );
        if ($wolf === null || $wolf['owner'] !== $playerId || $wolf['kind'] > P_PACK) {
            throw new BgaUserException('The wolf you selected is not valid!');
        }
        $isAlpha = (int)$wolf['kind'] === P_ALPHA;

        //Verify move is valid

        $deployedDens = (int)self::getUniqueValueFromDB("SELECT deployed_speed_dens FROM player_status WHERE player_id=$playerId");
        $maxDistance = WOLF_SPEED[$deployedDens];
        if (count($steps) > $maxDistance) {
            throw new BgaUserException('The selected tile is out of range');
        }

        [$targetX, $targetY] =
            self::checkPath([$wolf['x'], $wolf['y']], $steps, [T_WATER, T_CHASM]);

        $sharedKinds = $isAlpha ? [P_PACK, P_DEN] : [P_DEN];
        self::checkDestination($targetX, $targetY, $playerId, $sharedKinds, $terrain);

        $this->logDBUpdate("pieces", "x=$targetX, y=$targetY", "id=$wolfId", "x=$wolf[x], y=$wolf[y]");

        $this->addMovedWolf($wolfId);
        $remainingMoves = $this->logIncGameStateValue(G_MOVES_REMAINING, -1);
        $this->logIncStat(STAT_PLAYER_WOLVES_MOVED, 1, $playerId);

        $args = [
            'moveUpdate' => ['id' => $wolfId, 'steps' => $steps]
        ];
        $this->logNotification(clienttranslate('${player_name} undoes a movement'), $args);

        $args = array_merge($args, [
            'player_name' => self::getActivePlayerName(),
            'pieceIcon' => "$playerId,$wolf[kind]",
            'preserve' => ['pieceIcon']
        ]);

        self::notifyAllPlayers('update', clienttranslate('${player_name} moves a ${pieceIcon}'), $args);

        $pack = P_PACK;
        $packWolf = self::getUniqueValueFromDB(<<<EOF
            SELECT id FROM pieces 
            WHERE x=$targetX AND y=$targetY 
              AND kind=$pack AND owner <> $playerId
            EOF);

        if ($packWolf !== null) {
            $this->logSetGameStateValue(G_DISPLACEMENT_WOLF, $packWolf);
            $this->gamestate->nextState(TR_DISPLACE);
        } else {
            if ($remainingMoves > 0) {
                $this->gamestate->nextState(TR_MOVE);
            } else {
                $this->gamestate->nextState(TR_END_MOVE);
            }
        }
    }

    function skip(): void
    {
        $this->checkAction('skip');
        $this->gamestate->nextState(TR_END_MOVE);
    }

    function displace(array $steps): void
    {
        self::checkAction('displace');
        $playerId = self::getActivePlayerId();
        $wolfId = $this->getGameStateValue(G_DISPLACEMENT_WOLF);
        $wolf = self::getObjectFromDB("SELECT * FROM pieces WHERE id=$wolfId");

        ['x' => $wolfX, 'y' => $wolfY] = $wolf;
        [$x, $y] = self::checkPath([$wolfX, $wolfY], $steps, [T_WATER, T_CHASM], $wolf['owner']);

        self::checkDestination($x, $y, $wolf['owner']);

        if (count($steps) > 1) {
            ///TODO check that no shorter paths exist
        }

        $this->logDBUpdate("pieces", "x=$x, y=$y", "id=$wolfId", "x=$wolfX, y=$wolfY");

        $args = [
            'moveUpdate' => ['id' => $wolfId, 'steps' => $steps]
        ];

        $this->logNotification(clienttranslate('${player_name} undoes a displacement'), $args);

        $args = array_merge($args, [
            'player_name' => self::getActivePlayerName(),
            'pieceIcon' => "$wolf[owner],$wolf[kind]",
            'preserve' => ['pieceIcon'],
            'moveUpdate' => ['id' => $wolfId, 'steps' => $steps]
        ]);

        self::notifyAllPlayers('update', clienttranslate('${player_name} displaces a ${pieceIcon}'), $args);

        $remainingMoves = $this->getGameStateValue(G_MOVES_REMAINING);
        if ($remainingMoves > 0) {
            $this->gamestate->nextState(TR_MOVE);
        } else {
            $this->gamestate->nextState(TR_POST_ACTION);
        }
    }

    function howl(int $wolfId, int $x, int $y): void
    {
        self::checkAction('howl');
        $playerId = self::getActivePlayerId();
        $terrain = $this->getGameStateValue(G_SELECTED_TERRAIN);
        $wolf = self::getObjectFromDB("SELECT * FROM pieces WHERE id = $wolfId");

        ['dens' => $deployedDens, 'wolves' => $wolfIndex] = self::getObjectFromDB(<<<EOF
            SELECT deployed_howl_dens AS dens, deployed_wolves AS wolves  
            FROM player_status WHERE player_id=$playerId
            EOF);

        if ($wolf === null || (int)$wolf['kind'] !== P_ALPHA || $wolf['owner'] !== $playerId) {
            throw new BgaUserException('Invalid wolf!');
        }

        $lone = P_LONE;
        $maxRange = HOWL_RANGE[$deployedDens];
        $newKind = WOLF_DEPLOYMENT[$wolfIndex];

        $targetId = self::getUniqueValueFromDb(<<<EOF
            SELECT id FROM pieces NATURAL JOIN land
            WHERE x = $x AND y = $y AND kind = $lone AND terrain = $terrain 
                AND {$this->sql_hex_in_range('x', 'y',$wolf['x'],$wolf['y'],$maxRange)}
            EOF);

        if ($targetId === null) {
            throw new BgaUserException('Selected tile is invalid');
        }

        $this->logDBUpdate("pieces", "kind=$newKind, owner=$playerId", "id = $targetId", "kind=$lone, owner=NULL");
        $this->logDBInsert("moonlight_board", "(kind)", "($lone)");
        $this->logDBUpdate("player_status", "deployed_wolves=deployed_wolves + 1", "player_id=$playerId", "deployed_wolves=deployed_wolves - 1");

        $wolfScore = DEPLOYED_WOLF_SCORE[$wolfIndex];
        $this->logDBUpdate("player", "player_score = player_score + $wolfScore", "player_id = $playerId", "player_score = player_score - $wolfScore");

        $this->logIncStat(STAT_PLAYER_LONE_WOLVES_CONVERTED, 1, $playerId);

        $attributesUpdate = [
            'playerId' => $playerId,
            'deployedWolves' => 1
        ];
        $scoreUpdate = [
            'playerId' => $playerId,
            'increment' => $wolfScore
        ];

        $this->logNotification(clienttranslate('${player_name} returns the ${tokenIcon} back'), [
            'player_name' => self::getActivePlayerName(),
            'convertUpdate' => [
                'targetId' => $targetId,
                'newOwner' => null,
                'newKind' => P_LONE
            ],
            'attributesUpdate' => $attributesUpdate,
            'scoreUpdate' => $scoreUpdate,
            'tokenIcon' => 'wolf,1',
            'preserve' => ['tokenIcon']
        ]);

        self::notifyAllPlayers('update', clienttranslate('${player_name} converts a ${tokenIcon} into a ${pieceIcon}'), [
            'player_name' => self::getActivePlayerName(),
            'tokenIcon' => 'wolf,1',
            'pieceIcon' => "$playerId,$newKind",
            'preserve' => ['tokenIcon', 'pieceIcon'],
            'convertUpdate' => [
                'targetId' => $targetId,
                'newOwner' => $playerId,
                'newKind' => $newKind
            ],
            'attributesUpdate' => $attributesUpdate,
            'scoreUpdate' => $scoreUpdate
        ]);
        $this->gamestate->nextState(TR_POST_ACTION);
    }

    function placeDen(int $wolfId, ?int $direction, int $denType): void
    {
        self::checkAction('den');

        $playerId = self::getActivePlayerId();
        $denCol = 'deployed_' . DEN_COLS[$denType] . '_dens';
        $numDens = (int)self::getUniqueValueFromDB("SELECT $denCol FROM player_status WHERE player_id=$playerId");
        if ($numDens >= DEN_COUNT) {
            throw new BgaUserException('No more dens of this type!');
        }
        $terrain = $this->getGameStateValue(G_SELECTED_TERRAIN);
        $wolf = self::getObjectFromDB("SELECT * FROM pieces WHERE id=$wolfId");
        if ($wolf === NULL || (int)$wolf['kind'] !== P_ALPHA || $wolf['owner'] !== $playerId) {
            throw new BgaUserException('Invalid wolf selected!');
        }

        $denKind = P_DEN;
        $x = (int)$wolf['x'];
        $y = (int)$wolf['y'];
        if ($direction !== null) {
            [$dx, $dy] = HEX_DIRECTIONS[$direction];
            $x += $dx;
            $y += $dy;
        }

        $query = <<<EOF
            SELECT COUNT(*)  
            FROM land l NATURAL LEFT JOIN pieces p
            WHERE l.x = $x AND l.y = $y 
                AND l.terrain = $terrain
                AND (SELECT COUNT(*) FROM pieces WHERE x=l.x AND y=l.y) < 2
                AND (p.owner IS NULL OR p.kind < $denKind)
            EOF;
        if ((int)self::getUniqueValueFromDB($query) === 0) {
            throw new BgaUserException('Invalid hex selected!');
        }

        $deployedDens = (int)self::getUniqueValueFromDB("SELECT $denCol FROM player_status WHERE player_id=$playerId");

        $newId = $this->logDBInsert("pieces", "(owner, kind, x, y)", "($playerId, $denKind, $x, $y)");

        $this->logIncStat(STAT_PLAYER_DENS_PLACED, 1, $playerId);

        $args = [
            'player_name' => self::getActivePlayerName(),
            'pieceIcon' => "$playerId,$denKind",
            'preserve' => ['pieceIcon'],
            'buildUpdate' => [
                'id' => $newId,
                'owner' => $playerId,
                'x' => $x,
                'y' => $y,
                'kind' => P_DEN,
                'attribute' => $denType
            ],
        ];
        $args = array_merge($args, $this->giveDenAward($denType, $deployedDens, $playerId));

        $this->logNotification(clienttranslate('${player_name} takes the ${pieceIcon} back'), $args);

        self::notifyAllPlayers('update', clienttranslate('${player_name} places a ${pieceIcon}'), $args);

        $this->gamestate->nextState(TR_POST_ACTION);
    }

    function placeLair(int $wolfId, ?int $direcion): void
    {
        self::checkAction('lair');

        $playerId = self::getActivePlayerId();
        $numLairs = (int)self::getUniqueValueFromDB("SELECT deployed_lairs FROM player_status WHERE player_id=$playerId");
        if ($numLairs >= LAIR_COUNT) {
            throw new BgaUserException('No more lairs!');
        }
        $terrain = $this->getGameStateValue(G_SELECTED_TERRAIN);
        $wolf = self::getObjectFromDB("SELECT * FROM pieces WHERE id=$wolfId");
        if ($wolf === NULL || (int)$wolf['kind'] !== P_ALPHA || $wolf['owner'] !== $playerId) {
            throw new BgaUserException('Invalid wolf selected!');
        }

        $lairKind = P_LAIR;
        $x = (int)$wolf['x'];
        $y = (int)$wolf['y'];
        if ($direcion !== null) {
            [$dx, $dy] = HEX_DIRECTIONS[$direcion];
            $x += $dx;
            $y += $dy;
        }

        $den = P_DEN;
        $water = T_WATER;
        $query = <<<EOF
            SELECT id, region_id
            FROM pieces NATURAL LEFT JOIN land
            WHERE x = $x AND y = $y
                AND terrain = $terrain
                AND owner = $playerId 
                AND kind = $den
                AND (SELECT COUNT(*) 
                    FROM land 
                    WHERE x BETWEEN $x - 1 AND $x + 1
                        AND y BETWEEN $y - 1 AND $y + 1
                        AND {$this->sql_hex_in_range('x', 'y',$x,$y, 1)}
                    AND terrain=$water) > 0
            EOF;
        ["id" => $updateId, "region_id" => $regionId] = self::getObjectFromDB($query);
        if (is_null($updateId)) {
            throw new BgaUserException('No valid den at given hex!');
        }

        $query = <<<EOF
            SELECT COUNT(*)
            FROM pieces NATURAL JOIN land
            WHERE region_id = $regionId AND kind = $lairKind AND owner = $playerId
            EOF;
        $lairsInRegion = (int)self::getUniqueValueFromDB($query);
        if ($lairsInRegion > 0) {
            throw new BgaUserException('You already have a lair in this region!');
        }

        $alpha = P_ALPHA;
        $pack = P_PACK;
        $lairScore = LAIR_SCORE;
        $this->logDBInsert("moonlight_board", "(kind, player_id)", "($den, $playerId)");
        $this->logDBUpdate("pieces", "kind = $lairKind", "x = $x AND y = $y AND kind NOT IN ($alpha, $pack)", "kind = $den");
        $this->logDBUpdate("player_status", "deployed_lairs = deployed_lairs + 1, terrain_tokens = terrain_tokens + 1", "player_id = $playerId", "deployed_lairs = deployed_lairs - 1, terrain_tokens = terrain_tokens - 1");
        $this->logDBUpdate("player", "player_score = player_score + $lairScore", "player_id = $playerId", "player_score = player_score - $lairScore");

        $args = [
            'player_name' => self::getActivePlayerName(),
            'pieceIcon' => "$playerId,$lairKind",
            'preserve' => ['pieceIcon'],
            'buildUpdate' => [
                'id' => $updateId,
                'owner' => $playerId,
                'x' => $x,
                'y' => $y,
                'kind' => P_LAIR
            ],
            'attributesUpdate' => [
                'playerId' => $playerId,
                'terrainTokens' => 1,
                'deployedLairs' => 1
            ],
            'scoreUpdate' => [
                'playerId' => $playerId,
                'increment' => $lairScore
            ]
        ];

        $this->logNotification(clienttranslate('${player_name} takes the ${pieceIcon} back'), $args);

        self::notifyAllPlayers('update', clienttranslate('${player_name} places a ${pieceIcon}'), $args);

        $displaceWolf = self::getUniqueValueFromDB(<<<EOF
            SELECT id FROM pieces 
            WHERE x = $x AND y = $y 
              AND kind IN ($alpha, $pack) AND owner <> $playerId
            EOF);

        if ($displaceWolf !== null) {
            $this->logSetGameStateValue(G_DISPLACEMENT_WOLF, $displaceWolf);
            $this->logSetGameStateValue(G_MOVES_REMAINING, 0);
            $this->gamestate->nextState(TR_DISPLACE);
            return;
        }

        $this->logIncStat(STAT_PLAYER_DENS_UPGRADED, 1, $playerId);
        $this->gamestate->nextState(TR_POST_ACTION);
    }

    function dominate(int $wolfId, array $steps, int $targetId, int $denType): void
    {
        self::checkAction('dominate');
        $playerId = self::getActivePlayerId();
        $terrain = $this->getGameStateValue(G_SELECTED_TERRAIN);
        $wolf = self::getObjectFromDB("SELECT * FROM pieces WHERE id = $wolfId");
        $deployedDens = (int)self::getUniqueValueFromDB("SELECT deployed_howl_dens FROM player_status WHERE player_id = $playerId");
        $maxRange = HOWL_RANGE[$deployedDens];

        if (
            $wolf === NULL || $wolf['owner'] !== $playerId
            || (int)$wolf['kind'] !== P_ALPHA || count($steps) > $maxRange
        ) {
            throw new BgaUserException('Invalid wolf!');
        }

        [$targetX, $targetY] =
            self::checkPath([$wolf['x'], $wolf['y']], $steps);

        [$pack, $den] = [P_PACK, P_DEN];
        $target = self::getObjectFromDB(<<<EOF
            SELECT * FROM pieces AS target NATURAL JOIN land
            WHERE id=$targetId AND owner <> $playerId 
                AND terrain = $terrain AND kind IN ($pack, $den)
                AND x = $targetX AND y = $targetY 
                AND (SELECT COUNT(*) FROM pieces WHERE x = target.x AND y = target.y AND owner = target.owner) = 1
            EOF);
        if ($target === null) {
            throw new BgaUserException('Selected target is invalid!');
        }

        $oldKind = $target['kind'];
        $oldOwner = $target['owner'];

        if ((int)$oldKind === P_DEN) {
            if (!array_key_exists($denType, DEN_COLS)) {
                throw new BgaUserException('Must specify a den type if replacing a den!');
            }
            $denName = 'deployed_' . DEN_COLS[$denType] . '_dens';
            $numDens = (int)self::getUniqueValueFromDB("SELECT $denName FROM player_status WHERE player_id=$playerId");
            if ($numDens >= 4) {
                throw new BgaUserException('You have no more dens of this type to deploy!');
            }

            $updateArgs = $this->giveDenAward($denType, $numDens, $playerId);
            $newKind = P_DEN;
        } else {
            $numWolves = (int)self::getUniqueValueFromDB("SELECT deployed_wolves FROM player_status WHERE player_id=$playerId");
            if ($numWolves >= count(WOLF_DEPLOYMENT)) {
                throw new BgaUserException('You have no more wolves you can deploy!');
            }
            $this->logDBUpdate("player_status", "deployed_wolves=deployed_wolves + 1", "player_id=$playerId", "deployed_wolves=deployed_wolves - 1");
            // Update tie breaker
            $wolfScore = DEPLOYED_WOLF_SCORE[$numWolves];
            $this->logDBUpdate("player", "player_score = player_score + $wolfScore", "player_id = $playerId", "player_score = player_score - $wolfScore");
            $wolfType = WOLF_DEPLOYMENT[$numWolves];
            $newKind = $wolfType;

            $updateArgs = [
                'attributesUpdate' => [
                    'playerId' => $playerId,
                    'deployedWolves' => 1
                ],
                'scoreUpdate' => [
                    'playerId' => $playerId,
                    'increment' => $wolfScore
                ]
            ];
        }

        $this->logDBUpdate("pieces", "owner=$playerId, kind=$newKind", "id=$targetId", "owner=$oldOwner, kind=$oldKind");
        $this->logDBInsert("moonlight_board", "(player_id, kind)", "({$target['owner']}, {$target['kind']})");

        // Set wolves on board stat for attacker & defender in case that was updated
        $stat = (int)$target['kind'] === P_DEN ? STAT_PLAYER_DENS_DOMINATED : STAT_PLAYER_WOLVES_DOMINATED;
        $this->logIncStat($stat, 1, $playerId);

        $args = [
            'pieceIcon' => "$playerId,$target[kind]",
            'preserve' => ['pieceIcon'],
            'convertUpdate' => [
                'targetId' => $targetId,
                'newOwner' => $target['owner'],
                'newKind' => (int)$target['kind'],
                'attribute' => $denType
            ],
        ];
        $args = array_merge($args, $updateArgs);

        $this->logNotification('${player_name} takes the ${pieceIcon} back', $args);

        $args = [
            "player_name" => self::getActivePlayerName(),
            'pieceIcon0' => "$target[owner],$target[kind]",
            'pieceIcon1' => "$playerId,$newKind",
            'preserve' => ['pieceIcon0', 'pieceIcon1'],
            'convertUpdate' => [
                'targetId' => $targetId,
                'newOwner' => $playerId,
                'newKind' => $newKind,
                'attribute' => $denType
            ]
        ];
        $args = array_merge($args, $updateArgs);

        self::notifyAllPlayers('update', clienttranslate('${player_name} dominates a ${pieceIcon0} and places a ${pieceIcon1}'), $args);

        $this->gamestate->nextState(TR_POST_ACTION);
    }

    function extraTurn()
    {
        self::checkAction('extraTurn');

        $playerId = self::getActivePlayerId();
        $turnTokens = (int)self::getUniqueValueFromDB("SELECT action_tokens FROM player_status WHERE player_id=$playerId");
        if ($turnTokens == 0) {
            throw new BgaUserException('You have no extra turn tokens to play!');
        }
        $this->logDBUpdate("player_status", "action_tokens=action_tokens - 1", "player_id=$playerId", "action_tokens=action_tokens + 1");
        $this->logIncGameStateValue(G_ACTIONS_REMAINING, 1);

        $this->logIncStat(STAT_PLAYER_BONUS_ACTIONS_TAKEN, 1, $playerId);
        $this->logIncStat(STAT_BONUS_ACTIONS_TAKEN, 1);

        $args = [
            "player_name" => self::getActivePlayerName(),
            'attributesUpdate' => [
                "playerId" => $playerId,
                'actionTokens' => -1
            ]
        ];

        $this->logNotification('${player_name} cancels the bonus action', $args);

        $args['tokenIcon'] = 'action,1';
        $args['preserve'] = ['tokenIcon'];

        self::notifyAllPlayers('update', clienttranslate('${player_name} spends ${tokenIcon} to take another action'), $args);

        $this->gamestate->nextState(TR_SELECT_ACTION);
    }

    function endTurn()
    {
        self::checkAction('endTurn');
        $playerId = self::getActivePlayerId();
        self::incStat(1, STAT_PLAYER_TURNS_PLAYED, $playerId);
        self::incStat(1, STAT_TURNS_TAKEN);
        self::dbQuery("DELETE FROM turn_log");
        $this->gamestate->nextState(TR_CONFIRM_END);
    }

    function undo()
    {
        self::checkAction("undo");
        $this->undoAction();
    }

    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state arguments
    ////////////

    /*
        Here, you can create methods defined as "game state arguments" (see "args" property in states.inc.php).
        These methods function is to return some additional information that is specific to the current
        game state.
    */

    function argMove()
    {
        return [
            'numMoves' => $this->getGameStateValue(G_MOVES_REMAINING),
            'selectedTerrain' => $this->getGameStateValue(G_SELECTED_TERRAIN),
            'movedWolves' => $this->getMovedWolves()
        ];
    }

    function argActionSelection()
    {
        $returnArr = [];
        $returnArr["remainingActions"] = $this->getGameStateValue(G_ACTIONS_REMAINING);
        $returnArr["canUndo"] = $this->canUndo();
        return $returnArr;
    }

    function argTerrain()
    {
        return [
            "selectedTerrain" => $this->getGameStateValue(G_SELECTED_TERRAIN)
        ];
    }

    function argDisplaceSelection()
    {
        return [
            "displacementWolf" => $this->getGameStateValue(G_DISPLACEMENT_WOLF)
        ];
    }

    function argConfirmEnd()
    {
        $playerId = self::getActivePlayerId();
        return [
            "remainingTokens" => (int)self::getUniqueValueFromDB("SELECT action_tokens FROM player_status WHERE player_id=$playerId")
        ];
    }


    //////////////////////////////////////////////////////////////////////////////
    //////////// Game state actions
    ////////////

    /*
        Here, you can create methods defined as "game state actions" (see "action" property in states.inc.php).
        The action method of state X is called everytime the current game state is set to X.    */

    function stDraftResolution(): void
    {
        $wolvesDrafted = (int)self::getUniqueValueFromDB(
            "SELECT COUNT(*) FROM pieces WHERE owner IS NOT NULL"
        );
        $progress = (int)self::getGameStateValue(G_DRAFT_PROGRESS);

        if ($progress === 0 && $wolvesDrafted > 0) {
            $progress = intdiv($wolvesDrafted, 2);
        }

        $numPlayers = self::getPlayersNumber();

        $draftCompleted = $progress >= 2 * $numPlayers;

        if ($progress % $numPlayers !== 0 && !$draftCompleted) {
            if ($progress > $numPlayers) {
                $this->activePrevPlayer();
            } else {
                $this->activeNextPlayer();
            }
        }
        self::setGameStateValue(G_DRAFT_PROGRESS, $progress + 1);

        $this->gamestate->nextState($draftCompleted ? TR_DRAFT_END : TR_DRAFT_CONTINUE);
    }

    function stPostAction(): void
    {
        $remainingActions = $this->logIncGameStateValue(G_ACTIONS_REMAINING, -1);
        $this->logIncGameStateValue(G_ACTIONS_TAKEN, 1);
        $this->playerBonusTime();
        $this->doHunt();
        $noActionsRemaining = $remainingActions === 0;
        $this->gamestate->nextState($noActionsRemaining ? TR_CONFIRM_END : TR_SELECT_ACTION);
    }

    function stNextTurn(): void
    {
        $currentPhase = $this->getGameStateValue(G_MOON_PHASE);
        $nextPhase = $this->regionScoring();
        //Determine if the game should end
        if ($nextPhase >= count(PHASES)) {
            $alpha = P_ALPHA;
            $pack = P_PACK;
            self::dbQuery(<<<EOF
                UPDATE player
                NATURAL JOIN (SELECT owner AS player_id, COUNT(*) AS wolves FROM pieces WHERE kind IN ($alpha, $pack) GROUP BY player_id) as w
                NATURAL LEFT JOIN (SELECT player_id, COUNT(*) AS tokens FROM score_token GROUP BY player_id) as t
                SET player_score_aux = 100 * COALESCE(tokens, 0) + wolves
                EOF);
            $this->gamestate->nextState(TR_END_GAME);
        } else if ($nextPhase == $currentPhase) {
            $this->activeNextPlayer();
            $this->giveExtraTime(self::getActivePlayerId());

            $this->setGameStateValue(G_ACTIONS_REMAINING, 2);
            $this->setGameStateValue(G_ACTIONS_TAKEN, 0);
            $this->setGameStateValue(G_GIVEN_TIME, 0);
            $this->gamestate->nextState(TR_START_TURN);
        } else {
            $this->gamestate->nextState(TR_CONFIRM_END);
        }
    }

    function stPreActionSelection(): void
    {
        $this->logSetGameStateValue(G_SELECTED_TERRAIN, -1);
        $this->logSetGameStateValue(G_MOVES_REMAINING, -1);
        $this->logSetGameStateValue(G_MOVED_WOLVES, 0);
        $this->logSetGameStateValue(G_DISPLACEMENT_WOLF, -1);
        $this->newTurnLog();
    }

    function stMove(): void
    {
        $this->newTurnLog(ST_MOVE_SELECTION);
    }

    function stDisplace(): void
    {
        $this->newTurnLog(ST_DISPLACE);
    }

    function stHowl(): void
    {
        $this->newTurnLog(ST_HOWL_SELECTION);
    }

    function stDen(): void
    {
        $this->newTurnLog(ST_DEN_SELECTION);
    }

    function stLair(): void
    {
        $this->newTurnLog(ST_LAIR_SELECTION);
    }

    function stDominate(): void
    {
        $this->newTurnLog(ST_DOMINATE_SELECTION);
    }

    function stConfirmEnd(): void
    {
        $this->newTurnLog(ST_CONFIRM_END);
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

    function zombieTurn($state, $active_player): void
    {
        $statename = $state['name'];

        if ($state['type'] === "activeplayer") {
            $destination = $statename === 'draftWolves'?
                ST_DRAFT_RESOLUTION :
                ST_NEXT_TURN;
            $this->gamestate->jumpToState($destination);
        } else {
            throw new feException("Zombie mode not supported at this game state: $statename");
        }
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

    function upgradeTableDb($from_version): void
    {
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

    function ___debugAddTokens($type): void
    {
        $playerId = self::getActivePlayerId();
        $tokens = 100;
        self::DbQuery("UPDATE player_status SET ${type}_tokens = $tokens WHERE player_id = $playerId");
        self::notifyAllPlayers(
            'update',
            '${player_name} gained bonus tokens by cheating',
            [
                'player_name' => self::getActivePlayerName(),
                'newAttributes' => [
                    'playerId' => $playerId,
                    'terrain_tokens' => $tokens
                ]
            ]
        );
    }

    function ___debugRegionScoring(int $phase)
    {
        if ($phase > 2 || $phase < 0) {
            throw new BgaUserException("This ain't it");
        }

        $numPlayers = $this->getPlayersNumber();

        $this->setGameStateValue(G_MOON_PHASE, $phase);
        self::DbQuery("DELETE FROM moonlight_board");
        $entries = [];
        $loneWolfKind = P_LONE;
        for ($i = 0; $i < PHASES[$phase][$numPlayers]; $i++) {
            $entries[] = "($loneWolfKind)";
        }
        $args = implode(",", $entries);

        $query = "INSERT INTO moonlight_board (kind) VALUES $args";

        self::DbQuery($query);

        $this->regionScoring();
    }

    function ___debugAdvanceCalendar(int $count)
    {
        $kind = P_LONE;
        $kinds = implode(', ',
            array_fill(0, $count, "($kind)"));
        self::DbQuery("INSERT into moonlight_board(kind) VALUES $kinds");
        self::notifyAllPlayers(
            'message',
            "$count lone wolves added to calendar",
            []
        );
    }

    ///////////////////////////////////////////////////////////////////////////////////:
    ////////// Action Logging
    //////////
    function logDBUpdate($table, $updates, $condition, $reversions)
    {
        $query = "UPDATE $table SET $updates WHERE $condition";
        self::DbQuery($query);
        $reverseQuery = "UPDATE $table SET $reversions WHERE $condition";
        $this->updateNewestLog([
            "DB" => $reverseQuery
        ]);
    }

    function logDBInsert($table, $skeleton, $values): int
    {
        self::DbQuery("INSERT INTO $table $skeleton VALUES $values");
        $rowId = self::DbGetLastId();
        $reverseExpression = "DELETE FROM $table WHERE id=$rowId";
        $this->updateNewestLog([
            "DB" => $reverseExpression
        ]);
        return $rowId;
    }

    function logDBDelete($table, $condition)
    {
        $prevObj = self::getObjectFromDB("SELECT * FROM $table WHERE $condition");
        if (is_null($prevObj)) {
            return;
        }
        self::DbQuery("DELETE FROM $table WHERE $condition");

        $skeleton = '(`' . implode("`,`", array_keys($prevObj)) . '`)';
        $array_values = array_map(function ($val) {
            if (is_null($val)) {
                return 'NULL';
            }
            return $val;
        }, array_values($prevObj));
        $values = "(" . implode(",", $array_values) . ")";
        $reverseExpression = "INSERT INTO $table $skeleton VALUES $values";
        $this->updateNewestLog([
            'DB' => $reverseExpression
        ]);
    }

    function logNotification(string $message, array $args)
    {
        if (!array_key_exists("player_name", $args)) {
            $args['player_name'] = self::getActivePlayerName();
        }
        $this->updateNewestLog([
            'NOTIFY' => [
                'message' => $message,
                'args' => $args
            ]
        ]);
    }

    function logIncStat(string $statName, int $delta, $playerId = NULL)
    {
        $prevVal = self::getStat($statName, $playerId);
        self::incStat($delta, $statName, $playerId);
        $this->updateNewestLog([
            'STAT' => [
                'name' => $statName,
                'restore' => $prevVal,
                'player_id' => $playerId
            ]
        ]);
    }

    function logSetStat($statName, int $newVal, $playerId = NULL)
    {
        $prevVal = self::getStat($statName, $playerId);
        self::incStat($newVal, $statName, $playerId);
        $this->updateNewestLog([
            'STAT' => [
                'name' => $statName,
                'restore' => $prevVal,
                'player_id' => $playerId
            ]
        ]);
    }

    function logSetGamestateValue(string $label, int $value): int
    {
        $prevVal = $this->getGameStateValue($label);
        $this->setGameStateValue($label, $value);
        $this->updateNewestLog([
            'GAMESTATE_VALUE' => [
                'label' => $label,
                'restore' => $prevVal
            ]
        ]);
        return $value;
    }

    function logIncGamestateValue($label, int $delta): int
    {
        $prevVal = $this->getGameStateValue($label);
        $this->updateNewestLog([
            'GAMESTATE_VALUE' => [
                'label' => $label,
                'restore' => $prevVal
            ]
        ]);
        return $this->incGameStatevalue($label, $delta);
    }

    function updateNewestLog(array $data)
    {
        $jsonString = json_encode($data, JSON_HEX_QUOT + JSON_HEX_APOS + JSON_HEX_TAG + JSON_HEX_AMP);
        self::DbQuery("UPDATE turn_log SET data=JSON_ARRAY_APPEND(data, '$', CAST('$jsonString' AS JSON)) ORDER BY id DESC LIMIT 1");
    }

    function getNewestLog(bool $createLog = true): ?array
    {
        $currentTurn = self::getStat(STAT_TURNS_TAKEN);
        $newestLog = self::getObjectFromDB("SELECT id, data, state FROM turn_log WHERE turn=$currentTurn ORDER BY id DESC LIMIT 1");
        if (is_null($newestLog)) {
            if (!$createLog) {
                return NULL;
            }
            $newestLog = $this->newTurnLog();
        }
        return $newestLog;
    }

    function deleteNewestLog()
    {
        $currentTurn = self::getStat(STAT_TURNS_TAKEN);
        self::DbQuery("DELETE FROM turn_log WHERE turn=$currentTurn ORDER BY id DESC LIMIT 1");
    }

    function newTurnLog($state = ST_ACTION_SELECTION): array
    {
        $currentTurn = self::getStat(STAT_TURNS_TAKEN);
        self::DbQuery("INSERT INTO turn_log (turn, data, state) VALUES ($currentTurn, '[]', '$state')");
        $rowId = self::DbGetLastId();
        return ["id" => $rowId, 'data' => '[]'];
    }

    function undoAction()
    {
        $JSONData = [];
        while (count($JSONData) === 0) {
            $newestLog = $this->getNewestLog(false);

            if (is_null($newestLog)) {
                $this->gamestate->jumpToState(ST_ACTION_SELECTION);
                throw new BgaUserException('There are no more actions to undo!');
            }

            $JSONData = json_decode($newestLog['data'], true);
            $newState = $newestLog['state'];
            $this->deleteNewestLog();
        }

        foreach (array_reverse($JSONData) as $actionLog) {
            foreach ($actionLog as $actionType => $actionValue) {
                switch ($actionType) {
                    case 'DB':
                        self::DbQuery($actionValue);
                        break;
                    case 'NOTIFY':
                        self::notifyAllPlayers('undo', $actionValue['message'], $actionValue['args']);
                        break;
                    case 'STAT':
                        self::setStat((int)$actionValue['restore'], $actionValue['name'], $actionValue['player_id']);
                        break;
                    case 'GAMESTATE_VALUE':
                        $this->setGameStateValue($actionValue['label'], (int)$actionValue['restore']);
                        break;
                    default:
                        break;
                }
            }
        }

        $this->gamestate->jumpToState($newState);
    }

    function canUndo()
    {
        $currentTurn = self::getStat(STAT_TURNS_TAKEN);
        $thisRoundLogs = (int)self::getUniqueValueFromDB("SELECT COUNT(*) FROM turn_log WHERE turn=$currentTurn");
        return $thisRoundLogs > 1;
    }
}
