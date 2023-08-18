{OVERALL_GAME_HEADER}
<div id="wolves-active-tiles" class="hidden">
    <!-- BEGIN activeTile -->
    <div class="wolves-terrain-tile wolves-active-tile" data-index="{INDEX}"></div>
    <!-- END activeTile -->
</div>

<div id="wolves-game">
    <div id="wolves-land-container" style="max-width: {LAND_WIDTH}px">
        <div id="wolves-land" style="width: {LAND_WIDTH}px; height: {LAND_HEIGHT}px">
            <!-- BEGIN region -->
            <div id="wolves-region-{ID}" data-tile="{N}" class="wolves-region {ROTATE}" style="left: {CX}px; top: {CY}px"></div>
            <!-- END region -->

            <!-- BEGIN hex -->
            <div id="wolves-hex-{X}-{Y}" data-x="{X}" data-y="{Y}" class="wolves-hex wolves-hex-{TYPE}" style="left: {CX}px; top: {CY}px">
                <div id="wolves-hex-selector-{X}-{Y}" class="wolves-hex-selector"></div>
            </div>
            <!-- END hex -->

            <svg id="wolves-svg" viewBox="-500 -500 1000 1000" xmlns="http://www.w3.org/2000/svg">
                <path id="wolves-svg-path" fill="none" stroke="red"
                      stroke-width="10" stroke-linecap="round" stroke-dasharray="20 15" d=""/>
            </svg>
        </div>
    </div>

    <div id="wolves-boards">
        <div id="wolves-calendar">
            <!-- BEGIN calendarSpace -->
            <div id="wolves-calendar-space-{INDEX}" class="wolves-calendar-space" style="left: {CX}px; top: {CY}px"></div>
            <!-- END calendarSpace -->
        </div>

        <!-- BEGIN playerBoard -->
        <div id="wolves-player-container-{ID}" class="wolves-player-container">
            <h3 class="wolves-player-name" style="color: {COLOR}">{NAME}</h3>
            <div class="wolves-tile-container">
                <!-- BEGIN playerTile -->
                <div class="wolves-terrain-tile wolves-player-tile"></div>
                <!-- END playerTile -->
            </div>
            <div id="wolves-player-board-{ID}" class="wolves-player-board">
                <!-- BEGIN playerBoardSpaceGroup -->
                <div class="wolves-space-group wolves-{ITEM}-group">
                    <!-- BEGIN playerBoardSpace -->
                    <div class="wolves-player-board-space wolves-{KIND}-space"></div>
                    <!-- END playerBoardSpace -->
                </div>
                <!-- END playerBoardSpaceGroup -->
            </div>
        </div>
        <!-- END playerBoard -->
    </div>
</div>

<script type="text/javascript">
const jstpl_marker =
    `<div id="wolves-marker-\${id}"></div>`;
const jstpl_piece =
    `<div id="wolves-piece-\${id}" class="wolves-piece" data-kind="\${kind}" data-owner="\${owner}">
        <div class="wolves-piece-selector"></div>
        <div class="wolves-piece-sprite"></div>
    </div>`;
const jstpl_moon =
    `<div class="wolves-moon" data-phase="\${phase}"></div>`;
const jstpl_player_status =
    `<div class="wolves-player-status">
        <div>Move \${moveRange}</div>
        <div>Spread \${packSpread}</div>
        <div>Howl \${howlRange}</div>
        <div>Terrain \${terrainTokens}</div>
        <div>Turns \${turnTokens}</div>
     </div>`;
</script>  

{OVERALL_GAME_FOOTER}
