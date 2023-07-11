<?php


const ST_GAME_START = 1;
const ST_DRAFT_RESOLUTION = 2;
const ST_DRAFT_WOLVES = 3;
const ST_ACTION_SELECTION = 4;
const ST_HOWL_SELECTION = 5;
const ST_GAME_END = 99;

const A_MOVE = 0;
const A_HOWL = 1;
const A_DEN = 2;
const A_LAIR = 3;
const A_DOMINATE = 4;

const T_DESERT = 0;
const T_TUNDRA = 1;
const T_GRASS = 2;
const T_ROCK = 3;
const T_FOREST = 4;
const T_WATER = 5;

const P_ALPHA = 0;
const P_PACK = 1;
const P_DEN = 2;
const P_LAIR = 3;
const P_LONE = 4;
const P_PREY = 5;

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

const ACTION_COSTS = [
    A_MOVE => 1,
    A_HOWL => 2,
    A_DEN => 2,
    A_LAIR => 2,
    A_DOMINATE => 3
];

const TILE_TERRAIN_TYPES = 5;

