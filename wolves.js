/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Wolves implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * wolves.js
 *
 * Wolves user interface script
 * 
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

const PieceKind = Object.freeze({
    Alpha: 0,
    Pack: 1,
    Den: 2,
    Lair: 3,
    Lone: 4,
    Prey: 5,

    isMovable(kind) { return kind === this.Pack || kind === this.Alpha; },
    isStructure(kind) { return kind === this.Den || kind === this.Lair; }
});

const hexDirections = Object.freeze([[0, -1], [1, 0], [1, 1], [0, 1], [-1, 0], [-1, -1]]
    .map(([x, y]) => Object.freeze({x, y})));

const hexOffsets = Object.freeze(hexDirections
    .map(({x, y}) => ({x: x * 89.75, y: y * 103 - x * 51.5})))

const terrainNames = Object.freeze(["forest", "rock", "grass", "tundra", "desert", "water"]);

const attributeNames = Object.freeze(["howl", "pack", "speed"]);

const actionNames = Object.freeze(["move", "howl", "den", "lair", "dominate"]);
const actionCosts = Object.freeze({
    move: 1,
    howl: 2,
    den: 2,
    lair: 2,
    dominate: 3
});

class Attributes {
    constructor(data) {
        Object.assign(this, {
            homeTerrain: parseInt(data.home_terrain),
            deployedHowlDens: parseInt(data.deployed_howl_dens),
            deployedPackDens: parseInt(data.deployed_pack_dens),
            deployedSpeedDens: parseInt(data.deployed_speed_dens),
            deployedLairs: parseInt(data.deployed_lairs),
            deployedWolves: parseInt(data.deployed_wolves),
            terrainTokens: parseInt(data.terrain_tokens),
            actionTokens: parseInt(data.turn_tokens),
            preyData: parseInt(data.prey_data),
            tile0: parseInt(data.tile_0),
            tile1: parseInt(data.tile_1),
            tile2: parseInt(data.tile_2),
            tile3: parseInt(data.tile_3),
            tile4: parseInt(data.tile_4),
        })
    }

    attributeValue(min, upgrades) { return min + Math.floor((upgrades + 1)  / 2); }
    get moveRange() { return this.attributeValue(3, this.deployedSpeedDens); }
    get packSpread() { return this.attributeValue(2, this.deployedPackDens); }
    get howlRange() { return this.attributeValue(2, this.deployedHowlDens); }

    get tiles() {
        return ["homeTerrain", 0, 1, 2, 3, 4].map(p =>
            typeof p === "string" ? {
                front: parseInt(this[p])
            } : {
                front: (p + parseInt(this[`tile${p}`])) % 5,
                flipped: parseInt(this[`tile${p}`])
            })
    }

    update(data) {
        for (const name of Object.getOwnPropertyNames(data)) {
            const camelName = name.replace(/_[a-z0-9]/g, (match) => match.substring(1).toUpperCase());
            if (camelName in this) {
                this[camelName] += parseInt(data[name]);
            }
        }
    }

    flipTiles(tiles) {
        for (const index of tiles) {
            const field = `tile${index}`;
            if (field in this) {
                this[field] = 1 - this[field];
            }
        }
    }
}

class Queue {
    values = [];
    offset = 0;

    isEmpty() { return this.offset === this.values.length; }
    enqueue(value) { this.values.push(value); }
    dequeue() { return this.isEmpty() ? undefined : this.values[this.offset++]; }
}

class Pieces {
    idMap = new Map;
    ownerMap = new Map;
    hexMap = new Map;

    __hexIndex(x, y) {
        return x & 0xff | ((y & 0xff) << 8);
    }

    __push(map, key, value) {
        const array = map.get(key);
        if (array !== undefined) {
            array.push(value);
        } else {
            map.set(key, [value]);
        }
    }
    __pop(map, key, value) {
        const array = map.get(key);
        const index = array.indexOf(value);
        array.splice(index, 1);
    }

    *__yield(pieces, predicate) {
        if (pieces) {
            if (predicate) {
                for (const piece of pieces) {
                    if (predicate(piece)) {
                        yield piece;
                    }
                }
            } else {
                yield* pieces;
            }
        }
    }

    add(item) {
        const value = {
            id: parseInt(item.id),
            owner: item.owner,
            x: parseInt(item.x),
            y: parseInt(item.y),
            kind: parseInt(item.kind),
        }
        if (!this.idMap.has(value.id)) {
            this.idMap.set(value.id, value);
            this.__push(this.ownerMap, value.owner, value);
            this.__push(this.hexMap, this.__hexIndex(value.x, value.y), value);
        } else {
            console.warn(`Duplicate piece ID: ${value.id}`);
        }
        return value;
    }

    remove(id) {
        id = parseInt(id);
        const value = this.idMap.get(id);
        if (this.idMap.delete(id)) {
            this.__pop(this.hexMap, this.__hexIndex(value.x, value.y), value);
            this.__pop(this.ownerMap, value.owner, value);
            return true;
        }
        return false;
    }

    update(item) {
        const id = parseInt(item.id);
        const value = this.idMap.get(id);
        if (value) {
            if ("kind" in item) {
                value.kind = parseInt(item.kind);
            }

            if ("x" in item && "y" in item) {
                const oldHexIndex = this.__hexIndex(value.x, value.y);
                value.x = parseInt(item.x);
                value.y = parseInt(item.y);
                const newHexIndex = this.__hexIndex(value.x, value.y);
                if (newHexIndex !== oldHexIndex) {
                    this.__pop(this.hexMap, oldHexIndex, value);
                    this.__push(this.hexMap, newHexIndex, value);
                }
            }

            if ("owner" in item && item.owner !== value.owner) {
                this.__pop(this.ownerMap, value.owner, value);
                this.__push(this.ownerMap, item.owner, value);
                value.owner = item.owner;
            }
        } else {
            console.warn(`Unknown piece ID: ${item.id}`);
        }
    }

    getById(id) {
        return this.idMap.get(parseInt(id));
    }

    *getByOwner(owner, predicate) {
        yield* this.__yield(this.ownerMap.get(owner), predicate);
    }

    *getByHex(hex, predicate) {
        const hexIndex = this.__hexIndex(parseInt(hex.x), parseInt(hex.y));
        yield* this.__yield(this.hexMap.get(hexIndex), predicate);
    }

    *getByHexRange(hex, range, predicate) {
        range = parseInt(range);
        for (let x = hex.x - range; x <= hex.x + range; ++x) {
            for (let y = hex.y - range; y <= hex.y + range; ++y) {
                if (hexDistance(hex, {x, y}) < range) {
                    const hexIndex = this.__hexIndex(x, y);
                    yield* this.__yield(this.hexMap, hexIndex, predicate);
                }
            }
        }
    }

    *getByKind(kind, predicate) {
        if (Array.isArray(kind)) {
            for (const piece of this.idMap.values()) {
                if (kind.indexOf(piece.kind) >= 0 && (predicate === undefined || predicate(piece))) {
                    yield piece;
                }
            }
        } else {
            for (const piece of this.idMap.values()) {
                if (piece.kind === kind && (predicate === undefined || predicate(piece))) {
                    yield piece;
                }
            }
        }
    }
}

function clearTag(className) {
    const selector = `.${className}`;
    document.querySelectorAll(selector).forEach(node => {
        node.classList.remove(className);
    });
}

function getHexNode(hex) {
    return document.getElementById(`wolves-hex-${hex.x}-${hex.y}`);
}

function getPieceNode(id) {
    return document.getElementById(`wolves-piece-${id}`);
}

function getPieceHexNode(id) {
    const node = document.getElementById(`wolves-piece-${id}`);
    if (node) {
        return node.parentNode;
    }
}

function isHexPassable(hexNode) {
    const classList = hexNode.classList;
    return !classList.contains("wolves-hex-water") && !classList.contains("wolves-hex-chasm");
}

function hexAdd(hex1, hex2) {
    return {
        x: hex1.x + hex2.x,
        y: hex1.y + hex2.y
    };
}

function lerp(point1, point2, coef) {
    return {
        x: point1.x + coef * (point2.x - point1.x) ,
        y: point1.y + coef * (point2.y - point1.y)
    };
}

function hexDistance(from, to) {
    return Math.round(Math.abs(from.x - to.x) + Math.abs(from.y - to.y) + Math.abs(from.x - from.y - to.x + to.y)) / 2;
}

function hexDirection(from, to) {
    return hexDirections.findIndex(d => d.x === to.x - from.x && d.y === to.y - from.y);
}

function updateHexSharing(...hexNodes) {
    const classes = ["wolves-piece-top", "wolves-piece-bottom"];
    for (const node of hexNodes) {
        const pieces = Array.from(node.querySelectorAll(".wolves-piece"));
        if (pieces.length > 1) {
            pieces.sort((a, b) => parseInt(a.dataset.kind) - parseInt(b.dataset.kind) );
            classes.forEach((name, index) => pieces[index].classList.add(name));
        } else {
            pieces.forEach(p => p.classList.remove(...classes));
        }
    }
}

function* collectPaths(from, range) {
    const queue = new Queue;
    const visited = new Set;

    queue.enqueue({hex: from, steps: []});
    visited.add(JSON.stringify({x: from.x, y: from.y}));

    while (!queue.isEmpty()) {
        const {hex, steps} = queue.dequeue();
        if (range === undefined || steps.length < range) {
            let directionIndex = 0;
            for (const direction of hexDirections) {
                const newHex = hexAdd(hex, direction);
                const value = JSON.stringify(newHex);
                const node = getHexNode(newHex);

                if (node && !visited.has(value)) {
                    visited.add(value);
                    const newPath = {
                        node,
                        hex: newHex,
                        steps: [...steps, directionIndex]
                    };
                    const isPassable = yield newPath;
                    if (isPassable === undefined || isPassable) {
                        queue.enqueue(newPath);
                    }
                }
                ++directionIndex;
            }
        }
    }
}

function makeHexSelectable(hex, terrain) {
    document.getElementById("wolves-land").classList.add("wolves-selectable");
    let node = getHexNode(hex);
    if (node && (terrain === undefined || node.classList.contains(`wolves-hex-${terrainNames[terrain]}`))
        && !node.classList.contains("wolves-hex-water"))
    {
        node.classList.add("wolves-selectable");
    }
}

function prepareHowlSelection(playerId, pieces, terrain, range) {
    const alphaWolves = Array.from(pieces.getByOwner(playerId, p => p.kind === PieceKind.Alpha));
    const loneWolves = pieces.getByKind(PieceKind.Lone);

    for (const wolf of loneWolves) {
        if (alphaWolves.some(alpha => hexDistance(wolf, alpha) <= range)) {
            makeHexSelectable(wolf, terrain);
        }
    }
}

function prepareMoveSelection(playerId, pieces) {
    clearTag("wolves-selected");
    clearTag("wolves-selectable");
    const wolves = pieces.getByOwner(playerId, p => PieceKind.isMovable(p.kind));
    for (const wolf of wolves) {
        getPieceNode(wolf.id).classList.add("wolves-selectable");
    }
}

function prepareDenSelection(playerId, pieces, terrain) {
    const alphaWolves = pieces.getByOwner(playerId, p => p.kind === PieceKind.Alpha);
    for (const wolf of alphaWolves) {
        for (const direction of [{x: 0, y: 0}, ...hexDirections]) {
            const hex = hexAdd(wolf, direction);
            const otherPieces = Array.from(pieces.getByHex(hex));
            const canBuild = otherPieces.length < 2
                && otherPieces.every(p => p.owner === playerId && !PieceKind.isStructure(p.kind));
            if (canBuild) {
                makeHexSelectable(hex, terrain);
            }
        }
    }
}

function prepareLairSelection(playerId, pieces, terrain) {
    const alphaWolves = Array.from(pieces.getByOwner(playerId, p => p.kind === PieceKind.Alpha));
    const dens = pieces.getByOwner(playerId, p => p.kind === PieceKind.Den);
    for (const den of dens) {
        const canBuild = hexDirections.some(d => {
            const node = getHexNode(hexAdd(den, d));
            return node && node.classList.contains("wolves-hex-water");
        })
        if (canBuild && alphaWolves.some(alpha => hexDistance(alpha, den) <= 1)) {
            makeHexSelectable(den, terrain);
        }
    }
}

function prepareDominateSelection(playerId, range, terrain, pieces) {
    const terrainClass = `wolves-hex-${terrainNames[terrain]}`;
    const paths = [];

    function canDominate(piece) {
        return piece.owner !== playerId
            && getPieceHexNode(piece.id).classList.contains(terrainClass)
            && Array.from(pieces.getByHex(piece, p =>
                p.owner === piece.owner)).length === 1
    }

    const validPieces = pieces.getByKind([PieceKind.Den, PieceKind.Pack], canDominate);

    for (const piece of validPieces) {
        const iterable = collectPaths(piece, range);
        let item = iterable.next();

        while (!item.done) {
            const path = item.value;
            const alpha = pieces.getByHex(path.hex, p =>
                p.owner === playerId && p.kind === PieceKind.Alpha).next();

            if (!alpha.done) {
                path.targetId = piece.id;
                path.alphaId = alpha.value.id;
                paths.push(path);
                getPieceNode(piece.id).classList.add("wolves-selectable");
                break;
            }

            item = iterable.next();
        }
    }

    return paths;
}

function selectWolfToMove(wolf, range, terrain, pieces) {
    clearTag("wolves-selectable");
    getPieceNode(wolf.id).classList.add("wolves-selected");

    const paths = [];
    const iterable = collectPaths(wolf, range);
    let item = iterable.next();

    while (!item.done) {
        const path = item.value;
        const otherPieces = Array.from(pieces.getByHex(path.hex));

        const canStop = path.node.classList.contains(`wolves-hex-${terrainNames[terrain]}`)
            && otherPieces.length < 2
            && otherPieces.every(p =>
                p.owner === wolf.owner || p.kind === PieceKind.Den || wolf.kind === PieceKind.Alpha && p.kind === PieceKind.Pack);

        if (canStop) {
            paths.push(path);
            makeHexSelectable(path.hex);
        }

        item = iterable.next(isHexPassable(path.node));
    }

    return paths;
}

function selectWolfToDisplace(wolf, pieces) {
    clearTag("wolves-selected");
    clearTag("wolves-selectable");
    getPieceNode(wolf.id).classList.add("wolves-selected");

    const paths = [];
    const iterable = collectPaths(wolf);
    let item = iterable.next();
    let maxDistance = Number.MAX_VALUE;

    while (!item.done && item.value.steps.length <= maxDistance) {
        const path = item.value;
        const otherPieces = Array.from(pieces.getByHex(path.hex));

        path.canStop = otherPieces.length < 2 && otherPieces.every(p => p.owner === wolf.owner);

        if (path.canStop) {
            maxDistance = path.steps.length;
            paths.push(path);
            makeHexSelectable(path.hex);
        }

        item = iterable.next(isHexPassable(path.node));
    }

    return paths;
}

function buildPath(steps, fillet) {
    const svg = ["M "];
    let point = {x: 0, y: 0};
    let center = point;
    let extend = false;

    for (let i = 0; i < steps.length - 1; ++i) {
        if (!extend) {
            svg.push(`${point.x} ${point.y} `);
        }
        const nextPoint = hexAdd(center, hexOffsets[steps[i]]);

        if (steps[i + 1] === steps[i]) {
            point = nextPoint;
            center = nextPoint;
            extend = true;
        } else {
            const filletStart = lerp(nextPoint, point, fillet );
            svg.push(`L ${filletStart.x} ${filletStart.y} Q ${nextPoint.x} ${nextPoint.y} `);
            extend = false;
            center = nextPoint;
            point = lerp(nextPoint,
                hexAdd(nextPoint, hexOffsets[steps[i + 1]]),
                fillet);
        }
    }
    if (!extend) {
        svg.push(`${point.x} ${point.y} `);
    }
    const end = hexAdd(center, hexOffsets[steps[steps.length - 1]]);
    svg.push(`L ${end.x} ${end.y}`);
    return svg.join("");
}

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter"
], (dojo, declare)  => declare("bgagame.wolves", ebg.core.gamegui, {
    constructor() {
        console.log('wolves constructor');

        this.boardScale = 1.0;
        this.pieces = new Pieces;
        this.attributes = {};
        this.selectedAction = {};

        try {
            this.useOffsetAnimation = CSS.supports("offset-path", "path('M 0 0')");
        } catch (e) {
            this.useOffsetAnimation = false;
        }
    },

    /*
        setup:

        The method is called each time the game interface is displayed to a player, ie:
        _ when the game starts
        _ when a player refreshes the game page (F5)

        "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
    */

    setup(gameData) {
        console.log( "Starting game setup" );

        const playerCount = Object.keys(gameData.players).length;
        if (playerCount > 3) {
            document.getElementById("wolves-calendar").classList.add("wolves-reverse")
        }

        for (const region of gameData.regions) {
            const hex = getHexNode(region);
            const mask = parseInt(region.phase);
            for (let phase = 2; phase >= 0; --phase) {
                if (mask & (0x1 << phase)) {
                    dojo.place(this.format_block("jstpl_moon", {phase, regionId: region.id}), hex)
                }
            }
        }

        const scoringNode = document.getElementById(`wolves-calendar-space-${gameData.nextScoring - 1}`);
        if (scoringNode) {
            dojo.place(this.format_block("jstpl_marker", {id: "scoring"}), scoringNode);
        }

        for (let phase = 0; phase < 3; ++phase) {
            const moons = document.querySelectorAll(`.wolves-moon[data-phase="${phase}"]`);
            if (moons.length > 0) {
                moons.forEach(moon => moon.classList.add("wolves-upcoming"));
                break;
            }
        }

        for (const status of gameData.status) {
            const playerId = status.player_id;
            const attributes = new Attributes(status)
            this.attributes[playerId] = attributes;

            this.updateTiles(playerId);

            const node = document.getElementById(`player_board_${playerId}`);
            dojo.place(this.format_block("jstpl_player_status", attributes), node);

            function removeSpaces(groupName, count) {
                Array.from(document.querySelectorAll(`#wolves-player-board-${playerId} .wolves-${groupName}-group > *`))
                    .slice(0, count).forEach(s => s.classList.add("hidden"));
            }

            removeSpaces("pack", attributes.deployedPackDens);
            removeSpaces("speed", attributes.deployedSpeedDens);
            removeSpaces("howl", attributes.deployedHowlDens);
            removeSpaces("lair", attributes.deployedLairs);
            removeSpaces("wolf", attributes.deployedWolves);

            const preySpaces = Array.from(document.querySelectorAll(`#wolves-player-board-${playerId} .wolves-prey-space`));
            const prey = parseInt(status.prey_data);
            for (let i = 0; i < 5; ++i) {
                if ((prey & (0x1 << i)) !== 0) {
                    preySpaces.splice(0, 1)[0].dataset.preyType = i.toString();
                }
            }
        }

        gameData.pieces.forEach(this.addPiece, this);

        this.gameProgress = gameData.calendar.length;
        gameData.calendar.forEach(function(piece, index) {
            const homeTerrain = typeof piece.owner === "string" ? this.attributes[piece.owner].homeTerrain : "N/A";
            const args = {
                id: `calendar-${index}`,
                owner: homeTerrain,
                kind: piece.kind,
                locationClass: ""
            };
            const node = document.getElementById(`wolves-calendar-space-${index}`);
            dojo.place(this.format_block("jstpl_piece", args), node);
        }, this);

        document.querySelectorAll(".wolves-active-tile").forEach(tile => {
            const index = tile.dataset.index;
            dojo.connect(tile, 'onclick', e => {
                dojo.stopEvent(e);
                this.onTileClick(index, tile);
            })
        });

        document.querySelectorAll(".wolves-hex").forEach(hex => {
            if (!hex.classList.contains("wolves-hex-water")) {
                const x = parseInt(hex.dataset.x);
                const y = parseInt(hex.dataset.y);
                dojo.connect(hex.children[0], "onclick", e => {
                    dojo.stopEvent(e);
                    this.onHexClick(x, y)
                });
                dojo.connect(hex.children[0], "mouseenter", e => {
                    if (this.onHexEnter(x, y)) {
                        dojo.stopEvent(e);
                    }
                })
                dojo.connect(hex.children[0], "mouseleave", e => {
                    if (this.onHexLeave(x, y)) {
                        dojo.stopEvent(e);
                    }
                })
            }
        });

        // Setup game notifications to handle (see "setupNotifications" method below)
        this.setupNotifications();

        console.log( "Ending game setup" );
    },

    addPiece(data) {
        const piece = this.pieces.add(data);
        const homeTerrain =
            typeof piece.owner === "string" ?
                this.attributes[piece.owner].homeTerrain :
            typeof data.prey_metadata === "string" ?
                31 - Math.clz32(parseInt(data.prey_metadata))  :
                "N/A";
        const node = getHexNode(piece);
        if (node) {
            const args = {
                id: piece.id,
                owner: homeTerrain,
                kind: piece.kind,
            };
            const newNode = dojo.place(this.format_block("jstpl_piece", args), node);
            dojo.connect(newNode, "onclick", e => {
                if (this.onPieceClick(piece.id)) {
                    dojo.stopEvent(e);
                }
            });
            updateHexSharing(node);
            return newNode;
        }
    },

    removePiece(id, progress) {
        const hexNode = getPieceHexNode(id);
        if (this.pieces.remove(id)) {
            const node = document.getElementById(`wolves-piece-${id}`);
            if (progress) {
                const index = this.gameProgress++;
                const args = {
                    id: `calendar-${index}`,
                    owner: node.dataset.owner,
                    kind: node.dataset.kind,
                    locationClass: ""
                };
                const calendarNode = document.getElementById(`wolves-calendar-space-${index}`);
                const newNode = dojo.place(this.format_block("jstpl_piece", args), calendarNode);

                this.animateTranslation(newNode, node);
            }
            dojo.destroy(node);
            updateHexSharing(hexNode);
        }
    },

    movePiece(id, steps) {
        const piece = this.pieces.getById(id);
        if (piece) {
            const source = getHexNode(piece);
            const destination = steps.reduce((hex, step) => hexAdd(hex, hexDirections[step]), piece);
            destination.id = id;

            this.pieces.update(destination);
            const node = getHexNode(destination);
            const pieceNode = getPieceNode(id);
            const sourceRect = pieceNode.getBoundingClientRect();
            node.appendChild(pieceNode);

            updateHexSharing(source, node);
            this.animateTranslation(pieceNode, sourceRect, this.boardScale);
        }
    },

    animateTranslation(node, source, scale) {
        scale ||= 1.0;
        const start = source instanceof HTMLElement ? source.getBoundingClientRect() : source;
        const end = node.getBoundingClientRect();
        const [dX, dY] = [(end.left - start.left) / scale, (end.top - start.top) / scale];

        if (this.useOffsetAnimation) {
            node.style.offsetPath = `path("M 0 0 Q ${-dX / 2 - dY / 4} ${-dY / 2 + dX / 4} ${-dX} ${-dY}")`;
            node.classList.add("wolves-moving");
            node.addEventListener("animationend", () => {
                node.classList.remove("wolves-moving");
                node.style.offsetPath = "unset";
            }, {once: true});
        } else {
            node.style.transform = `translate(${-dX}px, ${-dY}px)`;
            node.classList.add("wolves-moving-simple");
            node.addEventListener("animationend", () => {
                node.classList.remove("wolves-moving-simple");
                node.style.transform = "none";
            }, {once: true});
        }
    },

    animatePlacement(playerId, pieceNode, kind, attribute) {
        const groupName =
            kind === PieceKind.Den ? attributeNames[attribute] :
            kind === PieceKind.Lair ? "lair" :
                "wolf";
        const boardNode = document.querySelectorAll(`#wolves-player-board-${playerId} .wolves-${groupName}-group .wolves-player-board-space:not(.hidden)`)[0];
        if (boardNode) {
            this.animateTranslation(pieceNode, boardNode);
            boardNode.classList.add("hidden");
        }
    },

    restorePlayerBoardNode(playerId, kind, attribute) {
        const groupName =
            kind === PieceKind.Den ? attributeNames[attribute] :
            kind === PieceKind.Lair ? "lair" :
                    "wolf";
        const hiddenNodes = document.querySelectorAll(`#wolves-player-board-${playerId} .wolves-${groupName}-group .hidden`);
        if (hiddenNodes.length > 0) {
            hiddenNodes[hiddenNodes.length - 1].classList.remove("hidden");
        }
    },

    ///////////////////////////////////////////////////
    //// Game & client states

    onEnteringState(stateName, state) {
        console.log(`Entering state: ${stateName}`);
        console.log(`Got args: ${JSON.stringify(state)}`);
        if (state.args && "selectedTerrain" in state.args) {
            this.selectedTerrain = parseInt(state.args.selectedTerrain);
        }

        if (this.isCurrentPlayerActive()) {
            const playerId = this.getActivePlayerId();
            const howlRange = this.activeAttributes().howlRange;
            switch (stateName) {
                case "actionSelection":
                    this.selectedAction = {};
                    clearTag("wolves-selected");
                    clearTag("wolves-selectable");
                    this.updateTiles(this.getActivePlayerId());
                    document.getElementById("wolves-active-tiles").classList.remove("hidden");
                    break;
                case "howlSelection":
                    prepareHowlSelection(playerId, this.pieces, this.selectedTerrain, howlRange);
                    break;
                case "moveSelection":
                    prepareMoveSelection(playerId, this.pieces);
                    break;
                case "denSelection":
                    prepareDenSelection(playerId, this.pieces, this.selectedTerrain);
                    break;
                case "lairSelection":
                    prepareLairSelection(playerId, this.pieces, this.selectedTerrain);
                    break;
                case "displaceWolf":
                    this.selectedPiece = parseInt(state.args.displacementWolf);
                    this.paths = selectWolfToDisplace(this.pieces.getById(this.selectedPiece), this.pieces);
                    break;
                case "dominateSelection":
                    this.paths = prepareDominateSelection(playerId, howlRange, this.selectedTerrain, this.pieces);
                    break;
                case "clientSelectTiles":
                    this.updateTiles(this.getActivePlayerId());
                    document.getElementById("wolves-active-tiles").classList.remove("hidden");
                    break;
                case "confirmEnd":
                    clearTag("wolves-selected");
                    clearTag("wolves-selectable");
                    break;
            }
        }
    },

    onLeavingState(stateName) {
        console.log(`Leaving state: ${stateName}`);

        switch (stateName) {
            case "clientSelectMoveTarget":
            case "howlSelection":
            case "denSelection":
            case "lairSelection":
            case "dominateSelection":
                clearTag("wolves-selectable");
                break;
            case "clientSelectTiles":
                document.getElementById("wolves-active-tiles").classList.add("hidden");
                break;
        }
    },

    onUpdateActionButtons(stateName, args) {
        console.log(`onUpdateActionButtons: ${stateName}`);

        if (this.isCurrentPlayerActive()) {
            switch (stateName) {
                case "actionSelection":
                    this.ensureButtonSet({
                        move: _("🐾 Move"),
                        howl: _("🌕 Howl"),
                        den: _("🕳 Den"),
                        lair: _("🪨 Lair"),
                        dominate: _("🐺 Dominate")
                    }, this.onSelectAction);
                    break;
                case "moveSelection":
                    this.ensureButton("wolves-skip-move", "Skip", "onSkipAction", null, null, "red");
                    break;
                case "clientSelectTiles":
                    const flippedTiles = this.selectedAction.tiles.size;
                    const remainingCost = this.selectedAction.cost - flippedTiles;
                    let tokens = this.activeAttributes().terrainTokens;
                    const text = tokens && tokens >= remainingCost ?
                        _(`Flip ${flippedTiles} tiles (${remainingCost} tokens)`) :
                        _(`Flip ${flippedTiles} tiles`);
                    this.ensureButton("wolves-action-flip", text, "onFlipTiles");
                    if (tokens < remainingCost) {
                        dojo.addClass("wolves-action-flip", "disabled");
                    }
                //fallthrough
                case "clientSelectMoveTarget":
                    this.ensureButton("button_cancel", _("Cancel"), "onCancel", null, null, "red");
                    break;
                case "clientSelectTerrain":
                    for (const terrain of terrainNames) {
                        if (terrain !== "water") {
                            this.ensureButton(`wolves-action-select-${terrain}`, terrain, "onSubmitTerrain");
                        }
                    }
                    this.ensureButton("button_cancel", _("Cancel"), "onCancel", null, null, "red");
                    break;
                case "clientSelectDenType":
                    this.ensureButtonSet({
                        howl: _("Howl Range"),
                        pack: _("Pack Spread"),
                        speed: _("Wolf Speed")
                    }, this.onSelectDen);
                    this.ensureButton("button_cancel", _("Cancel"), "onCancel", null, null, "red");
                    break;
                case "confirmEnd":
                    if (this.activeAttributes().actionTokens > 0) {
                        this.ensureButton("button_action", _("Use bonus action token"), "onBonusAction");
                    }
                    this.ensureButton("button_end", _("End turn"), "onEndTurn", null, null, "red");
                    break;
            }

            if (!stateName.startsWith("client")) {
                if (!("canUndo" in args) || args.canUndo ) {
                    this.ensureButton("button_undo", _("Undo"), "onUndo", null, null, "gray");
                }
            }
        }
    },

    ///////////////////////////////////////////////////
    //// Utility methods

    activeAttributes() {
        return this.attributes[this.getActivePlayerId()];
    },

    ensureButton(id, ...args) {
        if (!document.getElementById(id)) {
            this.addActionButton(id, ...args);
        }
    },

    ensureButtonSet(buttons, method) {
        Object.keys(buttons).forEach(name => {
            this.ensureButton(`button_${name}`, buttons[name], event => {
                dojo.stopEvent(event);
                method.call(this, name);
            });
        });
    },

    placeStructure(playerId, x, y, kind, extraArgs) {
        const wolf = this.pieces.getByOwner(playerId, p =>
            p.kind === PieceKind.Alpha && hexDistance(p, {x, y}) <= 1).next().value;

        const args = {lock: true, wolfId: wolf.id}
        const direction = hexDirection(wolf, {x, y});
        if (direction >= 0) {
            args.direction = direction.toString();
        }
        Object.assign(args, extraArgs);

        this.ajaxcall(`/wolves/wolves/${kind}.html`, args, this, () => {
            console.log(`${kind} placement completed`)
        });
    },

    dominatePiece(playerId, target, attribute) {
        const path = this.paths.filter(p => p.targetId === target.id)[0];
        const steps = path.steps.map(d => (parseInt(d) + 3) % hexDirections.length).reverse();

        this.ajaxcall("/wolves/wolves/dominate.html", {
            lock: true,
            wolfId: path.alphaId,
            targetId: target.id,
            denType: attribute || 0,
            steps: steps.join(',')
        }, () => {});
    },

    updateTiles(playerId) {
        const tileData = this.attributes[playerId].tiles;
        function setTiles(tiles) {
            tileData.forEach((tile, index) => {
                tiles[index].dataset.x = tile.front
                tiles[index].dataset.y = "flipped" in tile ? tile.flipped + 1 : 0;
            });
        }

        if (playerId === this.getActivePlayerId()) {
            setTiles(document.querySelectorAll(".wolves-active-tile"));
        }

        setTiles(document.querySelectorAll(`#wolves-player-container-${playerId} .wolves-player-tile`));
    },

    updateStatus(playerId) {
        const statusNode = document.querySelector(
            `#player_board_${playerId} .wolves-player-status`);
        if (statusNode) {
            dojo.destroy(statusNode);
            const node = document.getElementById(`player_board_${playerId}`);
            dojo.place(this.format_block("jstpl_player_status", this.attributes[playerId]), node);
        }
    },

    ///////////////////////////////////////////////////
    //// Player's action

    onHexEnter(x, y) {
        if (this.checkAction("clientMove", true)
            || this.checkAction("displace", true))
        {
            const path = this.paths.filter(({hex}) => hex.x === x && hex.y === y)[0];
            if (path && this.selectedPiece) {
                const src = getPieceHexNode(this.selectedPiece)
                const from = {
                    x: parseInt(src.style.left) + 51,
                    y: parseInt(src.style.top) + 56
                };
                const svg = document.getElementById("wolves-svg");
                svg.style = `left: ${from.x - 500}px; top: ${from.y - 500}px; position: absolute; z-index: 100; pointer-events: none; width: 1000px; height: 1000px`;
                document
                    .getElementById("wolves-svg-path")
                    .setAttribute("d", buildPath(path.steps, 0.3));
                return true;
            }
        }
        document
            .getElementById("wolves-svg-path")
            .setAttribute("d", "");
        return false;
    },

    onHexLeave(x, y) {
        document
            .getElementById("wolves-svg-path")
            .setAttribute("d", "");
    },

    onPieceClick(id) {
        console.log(`Click piece(${id})`);
        const pieceNode = getPieceNode(id);
        if (pieceNode.classList.contains("wolves-selectable")) {
            let playerId = this.getActivePlayerId();
            if (this.checkAction("move", false)) {
                const wolf = this.pieces.getById(id);
                const moveRange = this.activeAttributes().moveRange;
                this.paths = selectWolfToMove(wolf, moveRange, this.selectedTerrain, this.pieces);
                if (this.paths) {
                    this.selectedPiece = id;
                    this.setClientState("clientSelectMoveTarget", {
                        descriptionmyturn: _("${you} must select the destination hex"),
                        possibleactions: ["clientMove"]
                    });
                    return true;
                }
            } else if (this.checkAction("dominate")) {
                const target = this.pieces.getById(id);

                if (target.kind === PieceKind.Den) {
                    this.selectedPiece = id;
                    this.setClientState("clientSelectDenType", {
                        descriptionmyturn: _("${you} must select the den type"),
                        possibleactions: ["clientDominate"]
                    });
                } else {
                    this.dominatePiece(playerId, target);
                }
                return true;
            }
        }
        return false;
    },

    onHexClick(x, y) {
        console.log(`Click hex(${x}, ${y})`);
        const hex = getHexNode({x, y});

        if (!hex.classList.contains("wolves-selectable")) {
            if (this.checkAction("draftPlace")) {
                this.ajaxcall("/wolves/wolves/draftPlace.html",
                    {lock: true, x, y},
                    this,
                    () => { console.log("draftPlace completed") });
            }
            return;
        }

        const playerId = this.getActivePlayerId();

        if (this.checkAction("clientMove", true)) {
            this.ajaxcall("/wolves/wolves/move.html", {
                lock: true,
                wolfId: this.selectedPiece,
                steps: this.paths.filter(({hex}) => hex.x === x && hex.y === y)[0].steps.join(',')
            }, () => {
                document
                    .getElementById("wolves-svg-path")
                    .setAttribute("d", "");
            });
        } else if (this.checkAction("howl", true)) {
            const howlRange = this.activeAttributes().howlRange;
            const wolfId = this.pieces.getByOwner(playerId, p =>
                p.kind === PieceKind.Alpha && hexDistance(p, {x, y}) <= howlRange).next().value.id;

            this.ajaxcall("/wolves/wolves/howl.html", {
                lock: true, wolfId, x, y
            }, this, () => { console.log("howl completed") });
        } else if (this.checkAction("den", true)) {
            this.selectedHex = {x, y};
            this.setClientState("clientSelectDenType", {
                descriptionmyturn: _("${you} must select the den type"),
                possibleactions: ["clientDen"]
            });
        } else if (this.checkAction("lair", true)) {
            this.placeStructure(playerId, x, y, "lair");
        } else if (this.checkAction("displace")) {
            this.ajaxcall("/wolves/wolves/displace.html", {
                lock: true,
                steps: this.paths.filter(({hex}) => hex.x === x && hex.y === y)[0].steps.join(',')
            }, () => {
                document
                    .getElementById("wolves-svg-path")
                    .setAttribute("d", "");
            });
        }
    },

    onSelectAction(action) {
        if (!this.checkAction("selectAction")) {
            return;
        }
        console.log(`Submitting action (${action})`);
        this.selectedAction = { name: action, cost: actionCosts[action], tiles: new Set() };
        this.setClientState("clientSelectTiles", {
            descriptionmyturn: _(`\${you} must select ${this.selectedAction.cost} matching tiles`),
            possibleactions: ["clientSelectTile"]
        });
    },

    onTileClick(index, tile) {
        console.log(`Clicked tile (${index})`);
        if (!this.checkAction("clientSelectTile")) {
            return;
        }
        console.log(this.selectedAction);

        if (!this.selectedAction.tiles.delete(index) && this.selectedAction.tiles.size < this.selectedAction.cost) {
            this.selectedAction.tiles.add(index);
            tile.classList.add("wolves-selected");
        } else {
            tile.classList.remove("wolves-selected");
        }
        this.setClientState("clientSelectTiles", {
            descriptionmyturn: _(`\${you} must select ${this.selectedAction.cost} matching tiles`)
        });
    },

    onFlipTiles() {
        if (this.selectedAction.tiles.size > 0) {
            this.ajaxcall("/wolves/wolves/selectAction.html", {
                lock: true,
                actionId: actionNames.indexOf(this.selectedAction.name),
                terrainTokens: this.selectedAction.cost - this.selectedAction.tiles.size,
                tiles: Array.from(this.selectedAction.tiles).join(',')
            }, this, () => this.selectedAction = {});
        } else {
            this.setClientState("clientSelectTerrain", {
                descriptionmyturn: _(`\${you} must select the terrain for the action`)
            });
        }
    },

    onSkipAction() {
        this.ajaxcall("/wolves/wolves/skip.html", {lock: true}, this, () => this.selectedAction = {})
    },

    onSubmitTerrain(event) {
        this.ajaxcall("/wolves/wolves/selectAction.html", {
            lock: true,
            actionId: actionNames.indexOf(this.selectedAction.name),
            terrainTokens: this.selectedAction.cost,
            forceTerrain: terrainNames.indexOf(event.target.innerText),
            tiles: ""
        }, this, () => this.selectedAction = {});
    },

    onSelectDen(attributeName) {
        const attribute = attributeNames.indexOf(attributeName);
        const playerId = this.getActivePlayerId();
        if (this.checkAction("clientDen", true)) {
            const hex = this.selectedHex;
            this.placeStructure(playerId, hex.x, hex.y, "den", {denType: attribute});
        } else if (this.checkAction("clientDominate", true)) {
            this.dominatePiece(playerId, this.pieces.getById(this.selectedPiece), attribute);
        }
    },

    onCancel(event) {
        dojo.stopEvent(event);
        console.log("cancelled");
        clearTag("wolves-selectable");
        clearTag("wolves-selected");
        this.restoreServerGameState();
    },

    onBonusAction(event) {
        dojo.stopEvent(event);
        this.ajaxcall("/wolves/wolves/extraTurn.html", {lock: true}, () => {});
    },

    onEndTurn(event) {
        dojo.stopEvent(event);
        this.ajaxcall("/wolves/wolves/endTurn.html", {lock: true}, () => {});
    },

    onUndo(event) {
        this.onCancel(event);
        this.ajaxcall("/wolves/wolves/undo.html", {lock: true}, () => {});
    },

    ///////////////////////////////////////////////////
    //// Reaction to cometD notifications

    setupNotifications() {
        console.log( 'notifications subscriptions setup' );
        dojo.subscribe("draft", this, "onDraftNotification");
        this.notifqueue.setSynchronous("draft", 100);

        dojo.subscribe("update", this, "onUpdateNotification");
        dojo.subscribe("undo", this, "onUndoNotification");

        dojo.subscribe("scoring", this, "onScoringNotification");
    },

    onDraftNotification(data) {
        console.log("Draft notification:");
        console.log(data);
        const {player_id: playerId, x, y, ids, kinds} = data.args;

        ids.forEach((id, index) => {
            this.addPiece({x, y, id, owner: playerId, kind: kinds[index]});
        });
    },

    onUpdateNotification(data) {
        console.log("Update notification:");
        console.log(data);

        const move = data.args.moveUpdate;
        if (move) {
            this.movePiece(move.id, move.steps);
        }

        const tiles = data.args.tilesUpdate;
        if (tiles) {
            const attributes = this.attributes[tiles.playerId];
            attributes.flipTiles(tiles.flippedTiles);
            this.updateTiles(tiles.playerId);
        }

        const convert = data.args.convertUpdate;
        if (convert) {
            const piece = this.pieces.getById(convert.targetId);
            this.removePiece(piece.id, true);
            const node = this.addPiece({
                id: piece.id,
                owner: convert.newOwner,
                x: piece.x,
                y: piece.y,
                kind: convert.newKind
            });
            if (node) {
                this.animatePlacement(convert.newOwner, node, convert.newKind, convert.attribute);
            }
        }

        const build = data.args.buildUpdate;
        if (build) {
            this.removePiece(build.id, true);
            const node = this.addPiece(build);
            if (node) {
                this.animatePlacement(build.owner, node, build.kind, build.attribute);
            }
        }

        const attribute = data.args.attributesUpdate;
        if (attribute) {
            const playerId = attribute.playerId;
            this.attributes[playerId].update(attribute);
            this.updateStatus(playerId);
        }

        const hunt = data.args.huntUpdate;
        if (hunt) {
            const source = getPieceNode(hunt.id).getBoundingClientRect();
            this.removePiece(hunt.id);
            const preyNode = document.querySelector(`#wolves-player-board-${hunt.hunter} .wolves-prey-space:not([data-prey-type])`);
            if (preyNode) {
                preyNode.dataset.preyType = (31 - Math.clz32(hunt.preyData)).toString();
                this.animateTranslation(preyNode, source);
            }
        }
    },

    onUndoNotification(data) {
        console.log("Undo notification:");
        console.log(data);

        function undoCalendar() {
            --this.gameProgress;
            const spacesCount = document.querySelectorAll(".wolves-calendar-space .wolves-piece").length;
            const space = document.getElementById(`wolves-calendar-space-${spacesCount - 1}`);
            space.removeChild(space.lastChild);
        }

        const move = data.args.moveUpdate;
        if (move) {
            const steps = move.steps.map(d => (parseInt(d) + 3) % hexDirections.length).reverse();
            this.movePiece(move.id, steps);
        }

        const tiles = data.args.tilesUpdate;
        if (tiles) {
            const attributes = this.attributes[tiles.playerId];
            attributes.flipTiles(tiles.flippedTiles);
            this.updateTiles(tiles.playerId);
        }

        const convert = data.args.convertUpdate;
        if (convert) {
            const piece = this.pieces.getById(convert.targetId);
            this.removePiece(piece.id, false);
            this.addPiece({
                id: piece.id,
                owner: convert.newOwner,
                x: piece.x,
                y: piece.y,
                kind: convert.newKind
            });
            undoCalendar.call(this);
            this.restorePlayerBoardNode(piece.owner, piece.kind, convert.attribute);
        }

        const build = data.args.buildUpdate;
        if (build) {
            this.removePiece(build.id, false);
            const kind = build.kind;
            if (kind === PieceKind.Lair) {
                build.kind = PieceKind.Den;
                this.addPiece(build);
                undoCalendar.call(this);
            }
            this.restorePlayerBoardNode(build.owner, kind, build.attribute);
        }

        const attribute = data.args.attributesUpdate;
        if (attribute) {
            Object.getOwnPropertyNames(attribute).forEach(name => {
                if (typeof attribute[name] === "number") {
                    attribute[name] *= -1;
                }
            });
            const playerId = attribute.playerId;
            this.attributes[playerId].update(attribute);
            this.updateStatus(playerId);
        }

        const hunt = data.args.huntUpdate;
        if (hunt) {
            const preyNode = Array.from(document.querySelectorAll(`#wolves-player-board-${hunt.hunter} .wolves-prey-space[data-prey-type]`)).pop();
            preyNode.removeAttribute("data-prey-type");

            this.addPiece({
                id: hunt.id,
                owner: null,
                x: hunt.x,
                y: hunt.y,
                kind: PieceKind.Prey,
                prey_metadata: hunt.preyData
            });
        }
    },

    onScoringNotification(data) {
        console.log("Scoring notification:");
        console.log(data);

        const scoring = data.args;

        for (const {regionId, winner} of scoring.awards) {
            const moonNode = document.querySelector(`.wolves-moon[data-region="${regionId}"]:last-child`);
            const playerBoard = document.getElementById(`#player_board_${winner}`);

            if (playerBoard) {
                //TODO move the token to the player board for display
            }

            moonNode.classList.add("wolves-disappearing");
            moonNode.addEventListener("animationend", () => {dojo.destroy(moonNode)}, {once: true})
        }

        if ("nextScoring" in scoring) {
            const marker = document.getElementById("wolves-marker-scoring");
            const bounds = marker.getBoundingClientRect();
            const nextScoringNode = document.getElementById(`wolves-calendar-space-${scoring.nextScoring - 1}`);

            nextScoringNode.appendChild(marker);
            this.animateTranslation(marker, bounds);
        }

        if ("nextPhase" in scoring) {
            const moons = document.querySelectorAll(`.wolves-moon[data-phase="${scoring.nextPhase}"]`);

            moons.forEach(moon => moon.classList.add("wolves-upcoming", "wolves-fade-in"));

            setTimeout(() => {
                moons.forEach(moon => moon.classList.remove("wolves-fade-in"));
            }, 0);
        }
    },

    onScreenWidthChange() {
        const container = document.getElementById("wolves-land-container");
        const land = document.getElementById("wolves-land");
        const areaWidth = container.getBoundingClientRect().width;
        const scale = Math.min(1.0, areaWidth / parseInt(land.style.width));
        container.style.height = `${parseInt(land.style.height) * scale}px`;
        land.style.transform = `scale(${scale})`;
        this.boardScale = scale;
    }
}));

