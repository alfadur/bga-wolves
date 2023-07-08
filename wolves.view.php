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
  
require_once(APP_BASE_PATH."view/common/game.view.php");
  
class view_wolves_wolves extends game_view {
    protected function getGameName(): string {
        // Used for translations and stuff. Please do not modify.
        return "wolves";
    }

  	function build_page($viewArgs): void {
        $land_names = [
            T_GRASS => 'grass',
            T_ROCK => 'rock',
            T_TUNDRA => 'tundra',
            T_DESERT => 'desert',
            T_FOREST => 'forest',
            T_WATER => 'water',
        ];

        $tile_order = [
            T_DESERT,
            T_TUNDRA,
            T_GRASS,
            T_ROCK,
            T_FOREST
        ];

        $this->page->begin_block('wolves_wolves', 'hex');
        foreach ($this->game->getLand() as $hex) {
            $this->page->insert_block('hex', [
                'X' => $hex['x'],
                'Y' => $hex['y'],
                'CX' => $hex['x'] * 89,
                'CY' => $hex['y'] * 103 - $hex['x'] * 51,
                'TYPE' => $land_names[$hex['terrain']]
            ]);
        }

        global $g_user;
        $current_player_id = $g_user->get_id();
        $player_tiles = $this->game->getPlayerTiles($current_player_id);
        $this->page->begin_block("wolves_wolves", 'tile');
        for($i=0; $i < 5; $i++){
            $tile_value = $player_tiles[0][$i];
            $order_index = (($i - $tile_value) % 6 + 6) % 6;
            $this->page->insert_block('tile', [
                'INDEX' => $i,
                'TYPE' => $land_names[$tile_order[$order_index]]
            ]);
        }
  	}
}
