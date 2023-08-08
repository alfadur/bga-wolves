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
        $this->page->begin_block('wolves_wolves', 'activeTile');
        $this->page->insert_block('activeTile', ['INDEX' => TILE_TERRAIN_TYPES]);
        for ($i = 0; $i < TILE_TERRAIN_TYPES; ++$i) {
            $this->page->insert_block("activeTile", ['INDEX' => $i]);
        }

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

        $maxCx = 0;
        $maxCy = 0;

        $this->page->begin_block('wolves_wolves', 'hex');
        foreach ($this->game->getLand() as $hex) {
            $cx = $hex['x'] * 89;
            $cy = $hex['y'] * 103 - $hex['x'] * 51;
            if ($maxCx < $cx) {
                $maxCx = $cx;
            }
            if ($maxCy < $cy) {
                $maxCy = $cy;
            }
            $this->page->insert_block('hex', [
                'X' => $hex['x'],
                'Y' => $hex['y'],
                'CX' => $cx,
                'CY' => $cy,
                'TYPE' => $this->game->terrainNames[$hex['terrain']]
            ]);
        }

        $this->tpl['LAND_WIDTH'] = $maxCx + 119;
        $this->tpl['LAND_HEIGHT'] = $maxCy + 103;

        $this->page->begin_block('wolves_wolves', 'calendarSpace');
        for ($i = 0; $i < 22; ++$i) {
            $x = 54 * (($i + 2) % 8) + 16;
            $y = 54 * (intdiv($i + 2, 8)) + 58;
            $this->page->insert_block('calendarSpace', [
                'INDEX' => $i,
                'CX' => $x,
                'CY' => $y
            ]);
        }

        $this->page->begin_block('wolves_wolves', 'playerTile');
        $this->page->begin_block('wolves_wolves', 'playerBoardSpace');
        $this->page->begin_block('wolves_wolves', 'playerBoardSpaceGroup');
        $this->page->begin_block('wolves_wolves', 'playerBoard');

        $players = $this->game->getPlayers();
        foreach ($players as $player) {
            $this->page->reset_subblocks("playerTile");

            $this->page->insert_block("playerTile", [
                'FRONT' => $player['terrain'],
                'BACK' => $player['terrain']
            ]);
            for ($i = 0; $i < TILE_TERRAIN_TYPES; ++$i) {
                $this->page->insert_block("playerTile", [
                    'FRONT' => $i,
                    'BACK' => ($i + 1) % TILE_TERRAIN_TYPES
                ]);
            }

            $this->page->reset_subblocks('playerBoardSpaceGroup');

            $reservePieces = [
                ['pack', 'den', DEN_COUNT],
                ['speed', 'den', DEN_COUNT],
                ['howl', 'den', DEN_COUNT],
                ['lair', 'lair', LAIR_COUNT],
                ['prey', 'prey', count(PREY_NAMES)],
                ['wolf', 'wolf', count(WOLF_DEPLOYMENT)]
            ];

            foreach ($reservePieces as [$item, $kind, $count]) {
                $this->page->reset_subblocks('playerBoardSpace');
                $args = ['KIND' => $kind];
                for ($i = 0; $i < $count; ++$i) {
                    $this->page->insert_block('playerBoardSpace', $args);
                }
                $this->page->insert_block('playerBoardSpaceGroup', ['ITEM' => $item]);
            }

            $this->page->insert_block('playerBoard', [
                'ID' => $player['id'],
                'NAME' => $player['name'],
                'COLOR' => "#${player['color']}",
            ]);
        }
    }
}
