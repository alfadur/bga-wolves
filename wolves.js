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

const action_names = ['move', 'howl', 'den', 'lair', 'dominate'] 
const action_costs = {
    'move': 1,
    'howl': 2,
    'den': 2,
    'lair': 2,
    'dominate': 3
}
define([
    "dojo","dojo/_base/declare",
    "ebg/core/gamegui",
    "ebg/counter"
],
function (dojo, declare) {
    return declare("bgagame.wolves", ebg.core.gamegui, {
        constructor: function() {
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
        
        setup: function(gameData) {
            console.log( "Starting game setup" );

            this.players = gameData.players;
            // Setting up player boards
            for (const player_id in this.players) {
                const player = gameData.players[player_id];
                         
                // TODO: Setting up players boards if needed
            }

            for (const piece_id in gameData.pieces) {
                const piece = gameData.pieces[piece_id];
                const color = typeof piece.owner === "string" ?
                    this.players[piece.owner].color : "000";

                this.piece_template({
                    x: piece.x,
                    y: piece.y,
                    color: color
                });
            }

            document.querySelectorAll(".wolves-hex").forEach(hex => {
                if (!hex.classList.contains("wolves-hex-water")) {
                    const match = hex.id.match(/wolves-hex-(\d+)-(\d+)/);
                    const x = parseInt(match[1]);
                    const y = parseInt(match[2]);
                    dojo.connect(hex, "onclick", e => {
                        dojo.stopEvent(e);
                        this.onHexClick(x, y)
                    });
                }
            });

            document.querySelectorAll(".action-button").forEach(button => {
                const match = button.id.match(/(.+)-button/);
                const action = match[1];
                dojo.connect(button, "onclick", e => {
                    dojo.stopEvent(e);
                    console.log(`Activating action ${action}`)
                    this.onSelectAction(action);
                })
            });

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
       

        ///////////////////////////////////////////////////
        //// Game & client states
        
        // onEnteringState: this method is called each time we are entering into a new game state.
        //                  You can use this method to perform some user interface changes at this moment.
        //
        onEnteringState: function( stateName, args )
        {
            console.log( 'Entering state: '+stateName );
            
            switch( stateName )
            {
            
            /* Example:
            
            case 'myGameState':
            
                // Show some HTML block at this game state
                dojo.style( 'my_html_block_id', 'display', 'block' );
                
                break;
           */
           
           
            case 'dummmy':
                break;
            }
        },

        // onLeavingState: this method is called each time we are leaving a game state.
        //                 You can use this method to perform some user interface changes at this moment.
        //
        onLeavingState: function( stateName )
        {
            console.log( 'Leaving state: '+stateName );
            
            switch( stateName )
            {
            
            /* Example:
            
            case 'myGameState':
            
                // Hide the HTML block we are displaying only during this game state
                dojo.style( 'my_html_block_id', 'display', 'none' );
                
                break;
           */
           
           
            case 'dummmy':
                break;
            }               
        }, 

        // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
        //                        action status bar (ie: the HTML links in the status bar).
        //        
        onUpdateActionButtons: function( stateName, args )
        {
            console.log( 'onUpdateActionButtons: '+stateName );
                      
            if( this.isCurrentPlayerActive() )
            {            
                switch( stateName )
                {

                case "client_selectTiles":
                    if(this.isCurrentPlayerActive()){
                        if(!$("button_cancel")){
                            this.addActionButton('button_cancel', _('Cancel'), "onCancel");
                        }
                    }
                    break;
/*               
                 Example:
 
                 case 'myGameState':
                    
                    // Add 3 action buttons in the action status bar:
                    
                    this.addActionButton( 'button_1_id', _('Button 1 label'), 'onMyMethodToCall1' ); 
                    this.addActionButton( 'button_2_id', _('Button 2 label'), 'onMyMethodToCall2' ); 
                    this.addActionButton( 'button_3_id', _('Button 3 label'), 'onMyMethodToCall3' ); 
                    break;
*/
                }
            }
        },        

        ///////////////////////////////////////////////////
        //// Utility methods
        
        /*
        
            Here, you can defines some utility methods that you can use everywhere in your javascript
            script.
        
        */

        cancelLocalStateEffects: function(){
            this.clientStateArgs = {};
            this.restoreServerGameState();
        },


        ///////////////////////////////////////////////////
        //// Player's action
        
        /*
        
            Here, you are defining methods to handle player's action (ex: results of mouse click on 
            game objects).
            
            Most of the time, these methods:
            _ check the action is possible at this game state.
            _ make a call to the game server
        
        */

        onHexClick: function(x, y) {
            console.log("Click (" + x + ", " + y + ")");
            if (!this.checkAction("draftPlace")) {
                return;
            }

            this.ajaxcall("/wolves/wolves/draftPlace.html", {
                lock: true,
                x: x,
                y: y
            }, this, () => {
                console.log("draftPlace completed")
            });
        },

        onSelectAction: function(action) {
            
            if(!this.checkAction("selectAction")){
                return;
            }

            console.log(`Submitting action (${action})`);
            this.clientStateArgs = {};
            this.clientStateArgs.action_id = action_names.indexOf(action);
            this.clientStateArgs.tiles = [];
            this.setClientState("client_selectTiles", {
                descriptionmyturn: _(`\${you} must select ${action_costs[action_names[this.clientStateArgs.action_id]]} matching tiles`)
            });
        },

        onSelectTile: function(tile) {

            console.log("tile click");
            console.log(JSON.stringify(this.clientStateArgs))
            if(this.clientStateArgs.action_id === undefined){
                return;
            }
            console.log(`Clicked tile (${tile})`);
            if(this.clientStateArgs.tiles.includes(tile)){
                this.clientStateArgs.tiles.splice(this.clientStateArgs.tiles.indexOf(tile), 1);
            }
            else{
                this.clientStateArgs.tiles.push(tile);
            }

            const requiredTiles = action_costs[action_names[this.clientStateArgs.action_id]];
            
            if(this.clientStateArgs.tiles.length === requiredTiles){
                console.log(this.clientStateArgs);
                this.ajaxcall("/wolves/wolves/selectAction.html", {
                    ...this.clientStateArgs,
                    lock: true,
                    tiles: this.clientStateArgs.tiles.join(',')

                });
                this.clientStateArgs = {};
            }
            else{
                this.setClientState("client_selectTiles", {
                    descriptionmyturn: _(`\${you} must select ${requiredTiles - this.clientStateArgs.tiles.length} matching tiles`)
                })
            }
        },

        onCancel: function(event) {
            dojo.stopEvent(event);
            console.log("cancelled");
            this.cancelLocalStateEffects()
        },

        ///////////////////////////////////////////////////
        //// Reaction to cometD notifications

        /*
            setupNotifications:
            
            In this method, you associate each of your game notifications with your local method to handle it.
            
            Note: game notification names correspond to "notifyAllPlayers" and "notifyPlayer" calls in
                  your wolves.game.php file.
        
        */
        setupNotifications: function() {
            console.log( 'notifications subscriptions setup' );
            dojo.subscribe("draft", this, "notif_draft");
            this.notifqueue.setSynchronous("draft", 100);
        },

        notif_draft: function(data) {
            console.log("Draft notification:");
            console.log(data);
            const args = data.args;
            this.piece_template({
                x: args.x,
                y: args.y,
                color: this.players[args.player_id].color
            });
        },

        piece_template: function(data) {
            console.log("Placing template:");
            console.log(data);
            const hex = document.querySelector("#wolves-hex-" + data.x + "-" + data.y);
            dojo.place(this.format_block("jstpl_hex_content", data), hex);
        }
   });             
});
