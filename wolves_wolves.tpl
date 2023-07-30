{OVERALL_GAME_HEADER}
<div id="wolves-land-container">
    <div id="wolves-land" style="width: {LAND_WIDTH}px; height: {LAND_HEIGHT}px">
    <!-- BEGIN region -->
        <div id="wolves-region-{ID}" data-tile="{N}" class="wolves-region {ROTATE}" style="left: {CX}px; top: {CY}px"></div>
    <!-- END region -->
    <!-- BEGIN hex -->
        <div id="wolves-hex-{X}-{Y}" data-x="{X}" data-y="{Y}" class="wolves-hex wolves-hex-{TYPE}" style="left: {CX}px; top: {CY}px">
            <div id="wolves-hex-selector-{X}-{Y}" class="wolves-hex-selector"></div>
        </div>
    <!-- END hex -->
    </div>
</div>
<div id="player-board">
    <div id="player-tiles">
        <!-- BEGIN tile -->
            <span id="player-tile-{INDEX}" style="margin-inline:0.5em;" class="player-tile" data-terrain="{TYPE}"></span>
        <!-- END tile -->
    </div>
</div>
<div id="wolves-boards">
    <div id="wolves-calendar">
        <!-- BEGIN calendarSpace -->
            <div id="wolves-calendar-space-{INDEX}" class="wolves-calendar-space" style="left: {CX}px; top: {CY}px"></div>
        <!-- END calendarSpace -->
    </div>
</div>
<script type="text/javascript">
const jstpl_hex_content =
    `<div id="wolves-piece-\${id}" class="wolves-piece \${locationClass}" data-kind="\${kind}" data-owner="\${owner}">
        <div class="wolves-piece-selector"></div>
        <div class="wolves-piece-sprite"></div>
    </div>`
const jstpl_player_status =
    `<div class="wolves-player-status">
        <div>Move \${moveRange}</div>
        <div>Spread \${packSpread}</div>
        <div>Howl \${howlRange}</div>
        <div>Terrain \${terrainTokens}</div>
        <div>Turns \${turnTokens}</div>
     </div>`
</script>  

{OVERALL_GAME_FOOTER}
