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

        $hex_width = 118;
        $hex_height = 102;

        $this->page->begin_block('wolves_wolves', 'region');
        foreach ($this->game->getRegions() as $region) {
            $offset = $region['rotated'] ? 1 : 3;
            $this->page->insert_block('region', [
                'ID' => $region['region_id'],
                'N' => $region['tile_number'],
                'CX' => intdiv(($region['center_x'] - $offset) * 3 * $hex_width, 4),
                'CY' => intdiv(($region['center_y'] * 2 - $region['center_x'] - 3) * $hex_height, 2),
                'ROTATE' => $region['rotated'] ? 'wolves-region-rotated' : ''
            ]);
        }

        $maxCx = 0;
        $maxCy = 0;

        $this->page->begin_block('wolves_wolves', 'hex');
        foreach ($this->game->getLand() as ['x' => $x, 'y' => $y, 'terrain' => $terrain, 'region_id' => $regionId]) {
            $cx = intdiv($x * 3 * $hex_width, 4);
            $cy = $y * $hex_height - $x * intdiv($hex_height, 2);
            if ($maxCx < $cx) {
                $maxCx = $cx;
            }
            if ($maxCy < $cy) {
                $maxCy = $cy;
            }
            $this->page->insert_block('hex', [
                'X' => $x,
                'Y' => $y,
                'CX' => $cx,
                'CY' => $cy,
                'REGION_ID' => $regionId,
                'TYPE' => $this->game->terrainNames[$terrain],
            ]);
        }

        $this->tpl['LAND_WIDTH'] = $maxCx + $hex_width;
        $this->tpl['LAND_HEIGHT'] = $maxCy + $hex_height;

        $this->page->begin_block('wolves_wolves', 'calendarSpace');
        for ($i = 0; $i < 30; ++$i) {
            $x = 84 * (($i + 2) % 8) + 24;
            $y = 84 * (intdiv($i + 2, 8)) + 92;
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

            for ($i = 0; $i <= TILE_TERRAIN_TYPES; ++$i) {
                $this->page->insert_block("playerTile");
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

            $terrain = count($players) === 2 ? "2p-$player[terrain]" : $player['terrain'];
            $this->page->insert_block('playerBoard', [
                'ID' => $player['id'],
                'NAME' => $player['name'],
                'COLOR' => "#${player['color']}",
                'TERRAIN' => $terrain
            ]);
        }
    }
}
