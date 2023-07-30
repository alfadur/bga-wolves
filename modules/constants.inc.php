<?php

const NOT_MOVED_WOLF = 'move_wolf';
const NOT_DISPLACE_WOLF = 'displace_wolf';
const NOT_HOWL = 'howl';
const NOT_PLACE_DEN = 'place_den';
const NOT_PLACE_LAIR = 'place_lair';
const NOT_DOMINATE = 'dominate';
const NOT_EXTRA_TURN = 'extra_turn';
const NOT_END_TURN = 'end_turn';

const ST_GAME_START = 1;
const ST_DRAFT_RESOLUTION = 2;
const ST_DRAFT_WOLVES = 3;
const ST_ACTION_SELECTION = 4;
const ST_HOWL_SELECTION = 5;
const ST_MOVE_SELECTION = 6;
const ST_DEN_SELECTION = 7;
const ST_LAIR_SELECTION = 8;
const ST_DOMINATE_SELECTION = 9;
const ST_DISPLACE = 10;
const ST_POST_ACTION = 11;
const ST_CONFIRM_END = 12;
const ST_NEXT_TURN = 13;
const ST_GAME_END = 99;

const A_MOVE = 0;
const A_HOWL = 1;
const A_DEN = 2;
const A_LAIR = 3;
const A_DOMINATE = 4;

const T_FOREST = 0;
const T_ROCK = 1;
const T_GRASS = 2;
const T_TUNDRA = 3;
const T_DESERT = 4;
const T_WATER = 5;

const P_ALPHA = 0;
const P_PACK = 1;
const P_DEN = 2;
const P_LAIR = 3;
const P_LONE = 4;
const P_PREY = 5;

const PR_DEER = 0b1;
const PR_BOAR = 0b10;
const PR_RACCOON = 0b100;
const PR_HARE = 0b1000;
const PR_MOOSE = 0b10000;

const G_SELECTED_TERRAIN = 'selected_terrain';
const G_ACTIONS_REMAINING = 'actions_remaining';
const G_MOVES_REMAINING = 'moves_remaining';
const G_MOVED_WOLVES = 'moved_wolves';
const G_DISPLACEMENT_WOLF = 'displacement_wolf';
const G_MOON_PHASE = 'moon_phase';
const G_FLIPPED_TILES = 'flipped_tiles';
const G_SPENT_TERRAIN_TOKENS = 'spent_terrain_tokens';

const TR_DRAFT_CONTINUE = 'draftContinue';
const TR_DRAFT_END = 'draftEnd';
const TR_DRAFT_PLACE = 'draftPlace';
const TR_HOWL_SELECT = 'howlSelect';
const TR_MOVE_SELECT = 'moveSelect';
const TR_DEN_SELECT = 'denSelect';
const TR_LAIR_SELECT = 'lairSelect';
const TR_DOMINATE_SELECT = 'dominateSelect';
const TR_HOWL = 'howl';
const TR_MOVE = 'move';
const TR_DISPLACE = 'displace';
const TR_END_MOVE = 'endMove';
const TR_DEN = 'den';
const TR_LAIR = 'lair';
const TR_DOMINATE = 'dominate';
const TR_POST_ACTION = 'postAction';
const TR_SELECT_ACTION = 'selectAction';
const TR_CONFIRM_END = 'confirmEnd';
const TR_NEXT_TURN = 'nextTurn';
const TR_START_TURN = 'startTurn';
const TR_END_GAME = 'endGame';
const TR_SKIP = 'skip';

const BOARD_SETUP = [
    2 => [
        ['center' => [8, 9]],
        ['center' => [6, 5]],
        ['center' => [3, 7]],
        ['center' => [10, 13]],
        ['center' => [13, 11]],
    ],
    3 => [
        ['center' => [8, 9], 'chasm' => true],
        ['center' => [11, 7]],
        ['center' => [4, 4], 'rotate' => true],
        ['center' => [1, 6], 'rotate' => true],
        ['center' => [5, 11]],
        ['center' => [8, 12], 'rotate' => true],
        ['center' => [13, 11]]
    ],
    4 => [
        ['center' => [10, 10], 'chasm' => true],
        ['center' => [13, 8]],
        ['center' => [6, 5], 'rotate' => true],
        ['center' => [3, 4]],
        ['center' => [3, 7], 'rotate' => true],
        ['center' => [7, 12]],
        ['center' => [10, 13], 'rotate' => true],
        ['center' => [17, 16]],
        ['center' => [15, 12]]
    ],
    5 => [
        ['center' => [10, 13], 'chasm' => true],
        ['center' => [13, 11]],
        ['center' => [9, 6], 'rotate' => true],
        ['center' => [6, 8], 'rotate' => true],
        ['center' => [3, 7]],
        ['center' => [3, 10], 'rotate' => true],
        ['center' => [7, 15]],
        ['center' => [9, 19]],
        ['center' => [10, 16], 'rotate' => true],
        ['center' => [17, 19]],
        ['center' => [15, 15]]
    ]
];

const HEX_COORDS = [
    [-3, -2], [-2, -2], [-1, -2],
    [-3, -1], [-2, -1], [-1, -1], [0, -1], [1, -1],
    [-3, 0], [-2,  0], [-1,  0], [0,  0], [1,  0],
    [-1, 1], [0,  1], [1,  1]
];

const CHASM_HEXES = [
    0, 1, 2,
    5, 5, 5, 3, 4,
    2, 3, 5, 5, 5,
    4, 0, 1
];

const CHASM_PALLETTE = [
    T_TUNDRA, T_ROCK, T_DESERT, T_GRASS, T_FOREST, T_WATER
];

const REGION_HEXES = [
    0, 0, 0,
    1, 2, 2, 3, 3,
    1, 1, 2, 5, 3,
    4, 4, 4
];

const REGION_PALETTES = [
    [T_GRASS, T_FOREST, T_TUNDRA, T_ROCK, T_DESERT, T_WATER],
    [T_ROCK, T_FOREST, T_GRASS, T_TUNDRA, T_DESERT, T_WATER],
    [T_FOREST, T_GRASS, T_DESERT, T_ROCK, T_TUNDRA, T_WATER],
    [T_GRASS, T_DESERT, T_TUNDRA, T_FOREST, T_ROCK, T_WATER],
    [T_DESERT, T_TUNDRA, T_ROCK, T_GRASS, T_FOREST, T_WATER],
    [T_DESERT, T_ROCK, T_GRASS, T_TUNDRA, T_FOREST, T_WATER],
    [T_TUNDRA, T_ROCK, T_FOREST, T_DESERT, T_GRASS, T_WATER],
    [T_FOREST, T_TUNDRA, T_DESERT, T_GRASS, T_ROCK, T_WATER],
    [T_ROCK, T_GRASS, T_FOREST, T_DESERT, T_TUNDRA, T_WATER],
    [T_TUNDRA, T_DESERT, T_ROCK, T_FOREST, T_GRASS, T_WATER],
];

const REGION_LONE_WOLVES = [
    [-1, -2], [-1, 1]
];

const REGION_PREY = [-2, -1];

// const PR_DEER = 0b1;
// const PR_BOAR = 0b10;
// const PR_RACCOON = 0b100;
// const PR_HARE = 0b1000;
// const PR_MOOSE = 0b10000;

const AVAILABLE_PREY = [
    2 => [
        ["type" => PR_RACCOON, "amt" => 1],
        ["type" => PR_HARE, "amt" => 1],
        ["type" => PR_DEER, "amt" => 1],
        ["type" => PR_MOOSE, "amt" => 1],
        ["type" => PR_BOAR, "amt" => 1]
    ],
    3 => [
        ["type" => PR_HARE, "amt" => 2],
        ["type" => PR_HARE, "amt" => 2],
        ["type" => PR_BOAR, "amt" => 2],
        ["type" => PR_DEER, "amt" => 2],
        ["type" => PR_MOOSE, "amt" => 2],
        ["type" => PR_RACCOON, "amt" => 2]
    ],
    4 => [
        ["type" => PR_HARE, "amt" => 2],
        ["type" => PR_BOAR, "amt" => 2],
        ["type" => PR_RACCOON, "amt" => 2],
        ["type" => PR_HARE, "amt" => 2],
        ["type" => PR_BOAR, "amt" => 2],
        ["type" => PR_RACCOON, "amt" => 2],
        ["type" => PR_DEER, "amt" => 2],
        ["type" => PR_MOOSE, "amt" => 2]
    ],
    5 => [
        ["type" => PR_HARE, "amt" => 2],
        ["type" => PR_BOAR, "amt" => 2],
        ["type" => PR_RACCOON, "amt" => 2],
        ["type" => PR_DEER, "amt" => 2],
        ["type" => PR_MOOSE, "amt" => 2],
        ["type" => PR_HARE, "amt" => 2],
        ["type" => PR_BOAR, "amt" => 2],
        ["type" => PR_RACCOON, "amt" => 2],
        ["type" => PR_DEER, "amt" => 2],
        ["type" => PR_MOOSE, "amt" => 2]
    ]
];


const ACTION_COSTS = [
    A_MOVE => 1,
    A_HOWL => 2,
    A_DEN => 2,
    A_LAIR => 2,
    A_DOMINATE => 3
];

const TILE_TERRAIN_TYPES = 5;

const HEX_DIRECTIONS = [
    [0, -1], [1, 0], [1, 1], [0, 1], [-1, 0], [-1, -1]
];

const WOLF_DEPLOYMENT = [
    P_PACK, P_PACK, P_ALPHA, P_PACK, P_PACK, P_ALPHA, P_PACK, P_PACK
];

const AW_TURN = 0;
const AW_TERRAIN = 1;

const HOWL_RANGE_AWARDS_2P = [
    NULL, AW_TURN, NULL, AW_TERRAIN
];

const HOWL_RANGE_AWARDS_REST = [
    NULL, AW_TERRAIN, NULL, AW_TURN
];

const HOWL_RANGE_AWARDS = [
    2 => HOWL_RANGE_AWARDS_2P,
    3 => HOWL_RANGE_AWARDS_REST,
    4 => HOWL_RANGE_AWARDS_REST,
    5 => HOWL_RANGE_AWARDS_REST
];

const HOWL_RANGE = [
    2, 3, 3, 4, 4
];

const PACK_SPREAD_AWARDS_2P = [
    NULL, AW_TURN, NULL, AW_TERRAIN
];

const PACK_SPREAD_AWARDS_REST = [
    NULL, AW_TURN, NULL, AW_TERRAIN
];

const PACK_SPREAD_AWARDS = [ 
    2 => PACK_SPREAD_AWARDS_2P,
    3 => PACK_SPREAD_AWARDS_REST,
    4 => PACK_SPREAD_AWARDS_REST,
    5 => PACK_SPREAD_AWARDS_REST
];

const PACK_SPREAD = [
    2, 3, 3, 4, 4
];

const WOLF_SPEED_AWARDS_2P = [
    NULL, AW_TURN, NULL, AW_TERRAIN
];

const WOLF_SPEED_AWARDS_REST = [
    NULL, AW_TERRAIN, NULL, AW_TURN
];

const WOLF_SPEED_AWARDS = [
    2 => WOLF_SPEED_AWARDS_2P,
    3 => WOLF_SPEED_AWARDS_REST,
    4 => WOLF_SPEED_AWARDS_REST,
    5 => WOLF_SPEED_AWARDS_REST
];

const WOLF_SPEED = [
    3, 4, 4, 5, 5
];

const DEN_COLS = [
    'howl', 'pack', 'speed'
];

const CRESCENT_DATES = [
    2 => 7,
    3 => 9,
    4 => 11,
    5 => 13
];

const HALF_DATES = [
    2 => 13,
    3 => 17,
    4 => 20,
    5 => 23
];

const FULL_DATES = [
    2 => 18,
    3 => 22,
    4 => 26,
    5 => 30
];

const PHASES = [
    CRESCENT_DATES,
    HALF_DATES,
    FULL_DATES
];

const M_NONE = 0;
const M_CRESCENT = 0b001;
const M_QUARTER = 0b010;
const M_FULL = 0b100;
const M_CRES_HALF = 0b011;
const M_QUARTER_FULL = 0b110;

const START_MOON_PHASES = [
    2 => [
        M_CRESCENT,
        M_FULL,
        M_FULL,
        M_CRES_HALF,
        M_QUARTER_FULL
    ],
    3 => [
        M_CRESCENT,
        M_CRESCENT,
        M_QUARTER,
        M_QUARTER,
        M_FULL,
        M_FULL
    ],
    4 => [
        M_CRESCENT,
        M_CRESCENT,
        M_QUARTER,
        M_QUARTER,
        M_QUARTER,
        M_FULL,
        M_FULL,
        M_FULL
    ],
    5 => [
        M_CRESCENT,
        M_CRESCENT,
        M_CRESCENT,
        M_QUARTER,
        M_QUARTER,
        M_QUARTER,
        M_QUARTER,
        M_FULL,
        M_FULL,
        M_FULL
    ]
];

const MOON_SCORES = [
    4, 6, 8
];