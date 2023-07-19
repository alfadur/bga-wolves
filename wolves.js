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

    isMovable(kind) {
        kind = parseInt(kind);
        return kind === this.Pack || kind === this.Alpha;
    }
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
    return Math.round(Math.abs(to.x - from.x) + Math.abs(to.y - from.y) + Math.abs(to.x - to.y - from.x + from.y)) / 2;
}

function collectPaths(from, range) {
    const queue = new Queue();
    const visited = new Set();

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

function objectForEach(object, f) {
    for (const property in object) {
        f(object[property], property);
    }
}

function objectFilter(object, f) {
    const result = [];
    for (const property in object) {
        const value = object[property];
        if (f(value, property)) {
            result.push(object[p])
        }
    }
    return result;
}

function prepareHowlSelection(playerId, pieces, range) {
    const loneWolves = objectFilter(pieces, p => parseInt(p.kind) === PieceKind.Lone);
    const alphaWolves = objectFilter(pieces, p => p.owner === playerId && parseInt(p.kind) === PieceKind.Alpha);
    loneWolves
        .filter(lone => alphaWolves.some(alpha => hexDistance(lone, alpha) <= range))
        .forEach(wolf => getPieceHexNode(wolf.id).classList.add("wolves-selectable"));
}

function prepareMoveSelection(playerId, pieces) {
    objectForEach(pieces, (piece, pieceId) => {
        if (piece.owner === playerId && PieceKind.isMovable(piece.kind)) {
            const node = getPieceNode(pieceId);
            node.classList.add("wolves-selectable");
        }
    });
}

function selectWolf(id) {
    id = parseInt(id);
    const sourceHex = getPieceHexNode(id);
    if (sourceHex) {
        const paths = collectPaths({
            x: parseInt(sourceHex.dataset.x),
            y: parseInt(sourceHex.dataset.y),
        }, 3);
        paths.forEach(path => {
            const hex = getHexNode(path.hex);
            hex.classList.add("wolves-passable");
        })
        clearTag("wolves-selectable");
        return paths;
    }
}

function addPiece(piece, color, templater) {
    const node = getHexNode(piece);
    if (node) {
        let locationClass = "";
        if (node.children.length > 0) {
            node.children[0].classList.add("wolves-hex-item-top");
            locationClass = "wolves-hex-item-bottom";
        }
        return templater(node, "jstpl_hex_content", {
            id: piece.id,
            x: piece.x,
            y: piece.y,
            color,
            kind: piece.kind,
            locationClass
        });
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

            // Here, you can init the global variables of your user interface
            // Example:
            // this.myGlobalValue = 0;
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
            this.templater = function(node, template, args) {
                return dojo.place(this.format_block(template, args), node);
            }.bind(this)

            // Setting up player boards
            for (const player_id in this.players) {
                const player = gameData.players[player_id];
                         
                // TODO: Setting up players boards if needed
            }

            for (const pieceId in gameData.pieces) {
                const piece = gameData.pieces[pieceId];
                const color = typeof piece.owner === "string" ?
                    gameData.players[piece.owner].color : "000";

                this.addPiece(piece, color, this.templater);
            }

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

        addPiece(piece, color, templater) {
            const node = addPiece(piece, color, templater);
            if (node) {
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
                switch (stateName) {
                    case "howlSelection":
                        prepareHowlSelection(this.getActivePlayerId(), this.gamedatas.pieces, 2);
                        break;
                    case "moveSelection":
                        prepareMoveSelection(this.getActivePlayerId(), this.gamedatas.pieces);
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

        ///////////////////////////////////////////////////
        //// Player's action

        onHexClick(x, y) {
            console.log(`Click hex(${x}, ${y})`);
            const hex = getHexNode({x, y});

            if (hex.classList.contains("wolves-selectable")) {
                clearTag("wolves-selectable");
                const wolfId = object.filter(this.gamedatas.pieces, p => hexDistance({x, y}, p) <= 2)[0].id;
                if (this.checkAction("howl")) {
                    this.ajaxcall("/wolves/wolves/howl.html", {
                        lock: true, wolfId, x, y
                    }, this, () => { console.log("howl completed") });
                }
            }
            if (hex.classList.contains("wolves-passable")) {
                if (this.checkAction("clientMove")) {
                    console.log(`Moving to (${x}, ${y})`);

                    clearTag("wolves-passable");
                    this.ajaxcall("/wolves/wolves/move.html", {
                        lock: true,
                        wolfId: this.selectedPiece,
                        path: this.paths.filter(({hex}) => hex.x === x && hex.y === y)[0].path.join(',')
                    });
                }
            } else {
                if (this.checkAction("draftPlace")) {
                    this.ajaxcall("/wolves/wolves/draftPlace.html", {
                        lock: true,
                        x: x,
                        y: y
                    }, this, () => { console.log("draftPlace completed") });
                }
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
                this.addPiece(
                    {x, y, id, kind: kinds[index]},
                    this.gamedatas.players[playerId].color,
                    this.templater);
            });
        },
   });             
});
