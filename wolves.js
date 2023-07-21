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

const actioNames = Object.freeze(['move', 'howl', 'den', 'lair', 'dominate']);
const actionCosts = Object.freeze({
    move: 1,
    howl: 2,
    den: 2,
    lair: 2,
    dominate: 3
});

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
        return this.idMap.get(id);
    }

    *getByOwner(owner, predicate) {
        yield* this.__yield(this.ownerMap.get(owner), predicate);
    }

    *getByHex(hex, predicate) {
        yield* this.__yield(this.hexMap.get(this.__hexIndex(parseInt(hex.x), parseInt(hex.y))), predicate);
    }

    *getByKind(kind, predicate) {
        for (const piece of this.idMap.values()) {
            if (piece.kind === kind && (predicate === undefined || predicate(piece))) {
                yield piece;
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

function hexAdd(hex1, hex2) {
    return {
        x: hex1.x + hex2.x,
        y: hex1.y + hex2.y
    };
}

function hexDistance(from, to) {
    return Math.round(Math.abs(from.x - to.x) + Math.abs(from.y - to.y) + Math.abs(from.x - from.y - to.x + to.y)) / 2;
}

function hexDirection(from, to) {
    return hexDirections.findIndex(d => d.x === to.x - from.x && d.y === to.y - from.y);
}

function collectPaths(from, range) {
    const queue = new Queue;
    const visited = new Set;

    queue.enqueue({hex: from, path: []});
    visited.add(JSON.stringify(from));

    while (!queue.isEmpty()) {
        const {hex, path} = queue.dequeue();

        if (path.length < range) {
            hexDirections.forEach((direction, index) => {
                const newHex = hexAdd(hex, direction);
                const value = JSON.stringify(newHex);

                if (!visited.has(value)) {
                    const node = getHexNode(newHex);

                    if (node && !node.classList.contains("wolves-hex-water")) {
                        queue.enqueue({hex: newHex, path: [...path, index]});
                        visited.add(value);
                    }
                }
            });
        }
    }

    return queue.values.slice(1);
}

function prepareHowlSelection(playerId, pieces, range) {
    const alphaWolves = Array.from(pieces.getByOwner(playerId, p => p.kind === PieceKind.Alpha));
    const loneWolves = pieces.getByKind(PieceKind.Lone);
    for (const wolf of loneWolves) {
        if (alphaWolves.some(alpha => hexDistance(wolf, alpha) <= range)) {
            getPieceHexNode(wolf.id).classList.add("wolves-selectable");
        }
    }
}

function prepareMoveSelection(playerId, pieces) {
    const wolves = pieces.getByOwner(playerId, p => PieceKind.isMovable(p.kind));
    for (const wolf of wolves) {
        getPieceNode(wolf.id).classList.add("wolves-selectable");
    }
}

function prepareDenSelection(playerId, pieces) {
    const alphaWolves = pieces.getByOwner(playerId, p => p.kind === PieceKind.Alpha);
    for (const wolf of alphaWolves) {
        for (const direction of [{x: 0, y: 0}, ...hexDirections]) {
            const hex = hexAdd(wolf, direction);
            const otherPieces = Array.from(pieces.getByHex(hex));
            const canBuild = otherPieces.length < 2
                && otherPieces.every(p => p.owner === playerId && !PieceKind.isStructure(p.kind));
            if (canBuild) {
                getHexNode(hex).classList.add("wolves-selectable");
            }
        }
    }
}

function prepareLairSelection(playerId, pieces) {
    const alphaWolves = Array.from(pieces.getByOwner(playerId, p => p.kind === PieceKind.Alpha));
    const dens = pieces.getByOwner(playerId, p => p.kind === PieceKind.Den);
    for (const den of dens) {
        const canBuild = hexDirections.some(d => {
            const node = getHexNode(hexAdd(den, d));
            return node && node.classList.contains("wolves-hex-water");
        })
        if (canBuild && alphaWolves.some(w => hexDistance(1, den) <= 1)) {
            getHexNode(den).classList.add("wolves-selectable");
        }
    }
}

function selectWolf(id) {
    const sourceHex = getPieceHexNode(id);
    if (sourceHex) {
        const paths = collectPaths({
            x: parseInt(sourceHex.dataset.x),
            y: parseInt(sourceHex.dataset.y),
        }, 3);
        clearTag("wolves-selectable");
        paths.forEach(path => {
            const hex = getHexNode(path.hex);
            hex.classList.add("wolves-selectable");
        })

        return paths;
    }
}

define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter"
],
function (dojo, declare) {
    return declare("bgagame.wolves", ebg.core.gamegui, {
        constructor() {
            console.log('wolves constructor');

            this.pieces = new Pieces;
        },
        
        /*
            setup:
            
            This method must set up the game user interface according to current game situation specified
            in parameters.
            
            The method is called each time the game interface is displayed to a player, ie:
            _ when the game starts
            _ when a player refreshes the game page (F5)
            
            "gamedatas" argument contains all datas retrieved by your "getAllDatas" PHP method.
        */
        
        setup(gameData) {
            console.log( "Starting game setup" );

            // Setting up player boards
            for (const player_id in this.players) {
                const player = gameData.players[player_id];
                         
                // TODO: Setting up players boards if needed
            }

            gameData.pieces.forEach(this.addPiece, this);

            document.querySelectorAll(".wolves-hex").forEach(hex => {
                if (!hex.classList.contains("wolves-hex-water")) {
                    const x = parseInt(hex.dataset.x);
                    const y = parseInt(hex.dataset.y);
                    dojo.connect(hex, "onclick", e => {
                        dojo.stopEvent(e);
                        this.onHexClick(x, y)
                    });
                }
            });

            // document.querySelectorAll(".action-button").forEach(button => {
            //     const match = button.id.match(/(.+)-button/);
            //     const action = match[1];
            //     dojo.connect(button, "onclick", e => {
            //         dojo.stopEvent(e);
            //         console.log(`Activating action ${action}`)
            //         this.onSelectAction(action);
            //     })
            // });

            document.querySelectorAll(".player-tile").forEach(tile => {
                const match = tile.id.match(/player-tile-(\d+)/);
                const index = match[1];
                dojo.connect(tile, 'onclick', e => {
                    dojo.stopEvent(e);
                    this.onSelectTile(index);
                })
            })

            // Setup game notifications to handle (see "setupNotifications" method below)
            this.setupNotifications();

            console.log( "Ending game setup" );
        },

        addPiece(data) {
            const piece = this.pieces.add(data);
            const color = typeof piece.owner === "string" ? this.gamedatas.players[piece.owner].color : "000";
            const node = getHexNode(piece);
            if (node) {
                let locationClass = "";
                if (node.children.length > 0) {
                    node.children[0].classList.add("wolves-hex-item-top");
                    locationClass = "wolves-hex-item-bottom";
                }
                const args = {
                    id: piece.id,
                    x: piece.x,
                    y: piece.y,
                    color,
                    kind: piece.kind,
                    locationClass
                };
                dojo.place(this.format_block("jstpl_hex_content", args), node);
                dojo.connect(node, "onclick", e => {
                    if (this.onPieceClick(piece.id)) {
                        dojo.stopEvent(e);
                    }
                });
            }
        },

        ///////////////////////////////////////////////////
        //// Game & client states
        
        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //
        onEnteringState(stateName, args) {
            console.log(`Entering state: ${stateName}`);
            console.log(`Got args: ${JSON.stringify(args)}`);

            if (this.isCurrentPlayerActive()) {
                const playerId = this.getActivePlayerId();
                switch (stateName) {
                    case "howlSelection":
                        prepareHowlSelection(playerId, this.pieces, 2);
                        break;
                    case "moveSelection":
                        prepareMoveSelection(playerId, this.pieces);
                        break;
                    case "denSelection":
                        prepareDenSelection(playerId, this.pieces);
                        break;
                    case "lairSelection":
                        prepareDenSelection(playerId, this.pieces);
                        break;
                }
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState(stateName) {
            console.log(`Leaving state: ${stateName}`);
            
            switch (stateName) {

            }               
        }, 

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //        
        onUpdateActionButtons(stateName, args) {
            console.log( 'onUpdateActionButtons: '+stateName );
                      
            if (this.isCurrentPlayerActive()) {
                switch (stateName) {
                    case "actionSelection":
                        if (this.isCurrentPlayerActive()) {
                            const buttons = {
                                move: "🐾 Move",
                                howl: "🌕 Howl",
                                den: "🕳 Den",
                                lair: "🪨 Lair",
                                dominate: "🐺 Dominate"
                            }

                            Object.keys(buttons).forEach(name => {
                                if(!$(`button_${name}`)){
                                    this.addActionButton(`button_${name}`, _(buttons[name]), () => {
                                        this.onSelectAction(name);
                                    });
                                }
                            })
                        }
                        break;

                    case "clientSelectTiles":
                        if (this.isCurrentPlayerActive()) {
                            if (!$("button_cancel")) {
                                this.addActionButton('button_cancel', _('Cancel'), "onCancel", null, null, 'red');
                            }
                        }
                        break;
                    case "clientSelectMoveTarget":
                        break;
                    case "confirmEnd":
                        if (this.isCurrentPlayerActive()) {
                            if (!$("button_end")) {
                                this.addActionButton("button_end", _("End turn"), "onEndTurn", null, null, 'red');
                            }
                        }
                        break;
                }
            }
        },        

        ///////////////////////////////////////////////////
        //// Utility methods
        
        /*
        
            Here, you can defines some utility methods that you can use everywhere in your javascript
            script.
        
        */

        cancelLocalStateEffects() {
            this.clientStateArgs = {};
            this.restoreServerGameState();
        },

        placeStructure(playerId, x, y, kind, extraArgs) {
            const wolf = this.pieces.getByOwner(playerId, p =>
                p.kind === PieceKind.Alpha && hexDistance(p, {x, y}) <= 1).next().value;

            const args = {lock: true, wolfId: wolf.id}
            const path = hexDirection(wolf, {x, y});
            if (path) {
                args.path = path.toString();
            }
            Object.assign(args, extraArgs);

            this.ajaxcall(`/wolves/wolves/${kind}.html`, args, this, () => {
                console.log(`${kind} placement completed`)
            });
        },

        ///////////////////////////////////////////////////
        //// Player's action

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

            clearTag("wolves-selectable");
            const playerId = this.getActivePlayerId();

            if (this.checkAction("clientMove", true)) {
                console.log(`Moving to (${x}, ${y})`);

                clearTag("wolves-passable");
                this.ajaxcall("/wolves/wolves/move.html", {
                    lock: true,
                    wolfId: this.selectedPiece,
                    path: this.paths.filter(({hex}) => hex.x === x && hex.y === y)[0].path.join(',')
                });
            } else if (this.checkAction("howl", true)) {
                const wolfId = this.pieces.getByOwner(playerId, p =>
                    p.kind === PieceKind.Alpha && hexDistance(p, {x, y}) <= 2).next().value.id;

                this.ajaxcall("/wolves/wolves/howl.html", {
                    lock: true, wolfId, x, y
                }, this, () => { console.log("howl completed") });
            } else if (this.checkAction("den", true)) {
                this.placeStructure(playerId, x, y, "den", {denType: 0});
            } else if (this.checkAction("lair")) {
                this.placeStructure(playerId, x, y, "lair");
            }
        },

        onPieceClick(id) {
            console.log(`Click piece(${id})`);
            const piece = getPieceNode(id);
            if (piece.classList.contains("wolves-selectable")) {
                this.paths = selectWolf(id);
                if (this.paths) {
                    this.selectedPiece = id;
                    this.setClientState("clientSelectMoveTarget", {
                        descriptionmyturn: _("${you} must select the destination hex"),
                        possibleactions: ["clientMove"]
                    });
                    return true;
                }
            }
            return false;
        },

        onSelectAction(action) {
            if(!this.checkAction("selectAction")){
                return;
            }

            console.log(`Submitting action (${action})`);
            this.clientStateArgs = {
                action_id: actioNames.indexOf(action),
                tiles: []
            };
            this.setClientState("client_selectTiles", {
                descriptionmyturn: _(`\${you} must select ${actionCosts[actioNames[this.clientStateArgs.action_id]]} matching tiles`)
            });
        },

        onSelectTile(tile) {
            console.log("tile click");
            console.log(JSON.stringify(this.clientStateArgs));
            if (this.clientStateArgs.action_id === undefined) {
                return;
            }
            console.log(`Clicked tile (${tile})`);
            if (this.clientStateArgs.tiles.includes(tile)) {
                this.clientStateArgs.tiles.splice(this.clientStateArgs.tiles.indexOf(tile), 1);
            } else {
                this.clientStateArgs.tiles.push(tile);
            }

            const requiredTiles = actionCosts[actioNames[this.clientStateArgs.action_id]];
            
            console.log(this.clientStateArgs.tiles.join(','));
            if (this.clientStateArgs.tiles.length === requiredTiles) {
                console.log(this.clientStateArgs);
                this.ajaxcall("/wolves/wolves/selectAction.html", {
                    lock: true,
                    action_id: this.clientStateArgs.action_id,
                    terrain_tokens: 0, //TODO: Implement this
                    tiles: this.clientStateArgs.tiles.join(',')

                });
                this.clientStateArgs = {};
            } else {
                this.setClientState("clientSelectTiles", {
                    descriptionmyturn: _(`\${you} must select ${requiredTiles - this.clientStateArgs.tiles.length} matching tiles`)
                });
            }
        },

        onCancel(event) {
            dojo.stopEvent(event);
            console.log("cancelled");
            this.cancelLocalStateEffects()
        },

        onEndTurn(event) {
            dojo.stopEvent(event);
            this.ajaxcall("/wolves/wolves/endTurn.html", {lock: true});
        },

        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        /*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your wolves.game.php file.
        
        */
        setupNotifications() {
            console.log( 'notifications subscriptions setup' );
            dojo.subscribe("draft", this, "onDraftNotify");
            this.notifqueue.setSynchronous("draft", 100);
        },

        onDraftNotify(data) {
            console.log("Draft notification:");
            console.log(data);
            const {player_id: playerId, x, y, ids, kinds} = data.args;

            ids.forEach((id, index) => {
                this.addPiece({x, y, id, owner: playerId, kind: kinds[index]});
            });
        },
   });             
});
