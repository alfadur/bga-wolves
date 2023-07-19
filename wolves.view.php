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
        $tile_order = [
            T_FOREST,
            T_ROCK,
            T_GRASS,
            T_TUNDRA,
            T_DESERT
        ];

        $this->page->begin_block('wolves_wolves', 'region');
        foreach ($this->game->getRegions() as $region) {
            $this->page->insert_block('region', [
                'ID' => $region['region_id'],
                'N' => $region['tile_number'],
                'CX' => $region['center_x'] * 89 - 268,
                'CY' => $region['center_y'] * 103 - $region['center_x'] * 51 - 154,
                'ROTATE' => $region['rotated'] ? 'wolves-region-rotated' : ''
            ]);
        }

        $this->page->begin_block('wolves_wolves', 'hex');
        foreach ($this->game->getLand() as $hex) {
            $this->page->insert_block('hex', [
                'X' => $hex['x'],
                'Y' => $hex['y'],
                'CX' => $hex['x'] * 89,
                'CY' => $hex['y'] * 103 - $hex['x'] * 51,
                'TYPE' => $this->game->terrainNames[$hex['terrain']]
            ]);
        }

        global $g_user;
        $current_player_id = $g_user->get_id();
        $player_tiles = $this->game->getPlayerTiles($current_player_id);
        $this->page->begin_block("wolves_wolves", 'tile');
        for($i=0; $i < TILE_TERRAIN_TYPES; $i++){
            $tile_value = $player_tiles[$i];
            $order_index = ($i + $tile_value) % TILE_TERRAIN_TYPES;
            $this->page->insert_block('tile', [
                'INDEX' => $i,
                'TYPE' => $this->game->terrainNames[$tile_order[$order_index]]
            ]);
        }
        $this->page->insert_block('tile', [
            'INDEX' => TILE_TERRAIN_TYPES,
            'TYPE' => $this->game->terrainNames[$tile_order[$player_tiles[TILE_TERRAIN_TYPES]]]
        ]);
  	}
}
