{OVERALL_GAME_HEADER}
<div id="wolves-active-tiles" class="hidden">
    <!-- BEGIN activeTile -->
    <div class="wolves-terrain-tile wolves-active-tile" data-index="{INDEX}"></div>
    <!-- END activeTile -->
</div>

<div id="wolves-game">
    <div id="wolves-land-container" style="max-width: {LAND_WIDTH}px">
        <div id="wolves-land" style="width: {LAND_WIDTH}px; height: {LAND_HEIGHT}px" data-y-offset="{Y_OFFSET}">
            <div id="wolves-regions">
                <!-- BEGIN region -->
                <div id="wolves-region-{ID}" data-tile="{N}" class="wolves-region {ROTATE}" style="left: {CX}px; top: {CY}px"></div>
                <!-- END region -->
            </div>

            <svg id="wolves-selection-svg" viewBox="0 0 {LAND_WIDTH} {LAND_HEIGHT}" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <pattern id="wolves-selection-fill-pattern" width="16" height="16"
                            patternUnits="userSpaceOnUse" patternTransform="rotate(45)">
                        <line stroke="red" stroke-width="10px" x2="5"/>
                    </pattern>
                </defs>
                <path id="wolves-selection-svg-path" stroke-width="8" stroke-linecap="round" d=""/>
            </svg>

            <svg id="wolves-svg" viewBox="-500 -500 1000 1000" xmlns="http://www.w3.org/2000/svg">
                <path id="wolves-svg-path" fill="none" stroke="red"
                      stroke-width="10" stroke-linecap="round" stroke-dasharray="20 15" d=""/>
            </svg>

            <!-- BEGIN hex -->
            <div id="wolves-hex-{X}-{Y}" data-x="{X}" data-y="{Y}" data-region-id="{REGION_ID}"
                    class="wolves-hex wolves-hex-{TYPE}" style="left: {CX}px; top: {CY}px">
                <div id="wolves-hex-selector-{X}-{Y}" class="wolves-hex-selector"></div>
            </div>
            <!-- END hex -->
        </div>
    </div>

    <div id="wolves-boards-padding"></div>

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
            <div id="wolves-player-board-{ID}" class="wolves-player-board" data-terrain="{TERRAIN}">
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
    `<div class="wolves-moon" data-region="\${regionId}" data-phase="\${phase}"></div>`;
const jstpl_player_status =
    `<div id="wolves-player-status-tiles-\${playerId}" class="wolves-player-status-tiles">
        <div class="wolves-player-status-terrain" data-terrain="\${homeTerrain}"></div>
        <div class="wolves-terrain-tile wolves-player-status-tile"></div>
        <div class="wolves-terrain-tile wolves-player-status-tile"></div>
        <div class="wolves-terrain-tile wolves-player-status-tile"></div>
        <div class="wolves-terrain-tile wolves-player-status-tile"></div>
        <div class="wolves-terrain-tile wolves-player-status-tile"></div>
    </div>
    <div class="wolves-player-status">
        <div class="wolves-status-icon" data-attribute="speed" data-value="\${moveRange}">
            <svg viewBox="-1 -1 11 11">
                <path d="M2.881,6.918c0.304,0.115 0.433,0.441 0.664,0.647c0.465,0.413 1.976,1.376 0.646,1.965c-0.589,0.262 -1.016,-0.355 -1.6,-0.308c-0.492,0.04 -0.869,0.519 -1.382,0.337c-1.444,-0.51 0.108,-1.576 0.574,-1.994c0.326,-0.292 0.558,-0.851 1.098,-0.647Zm1.196,0.281c-0.12,-0.106 -0.078,-0.423 -0.021,-0.699c0.04,-0.196 0.101,-0.396 0.176,-0.582c0.56,-1.368 1.604,0.183 0.543,1.221c-0.207,0.202 -0.578,0.167 -0.698,0.06Zm-2.809,-0.037c-0.12,0.106 -0.485,0.112 -0.715,-0.023c-1.278,-0.755 -0.017,-2.589 0.543,-1.221c0.075,0.186 0.136,0.386 0.176,0.582c0.057,0.276 0.116,0.555 -0.004,0.662Zm2.349,-2.907c0.548,0.327 0.474,1.317 0.26,1.808c-0.14,0.321 -0.448,0.668 -0.832,0.435c-0.387,-0.234 -0.228,-1.521 -0.079,-1.889c0.097,-0.241 0.373,-0.519 0.651,-0.354Zm-1.599,-0.023c0.65,0.242 0.767,2.721 -0.212,2.287c-0.566,-0.251 -0.579,-1.28 -0.418,-1.766c0.087,-0.261 0.292,-0.647 0.63,-0.521Zm5.327,-1.63c0.291,0.111 0.416,0.423 0.637,0.62c0.446,0.397 1.895,1.32 0.62,1.886c-0.566,0.25 -0.975,-0.342 -1.535,-0.296c-0.472,0.038 -0.834,0.497 -1.326,0.323c-1.384,-0.489 0.104,-1.512 0.551,-1.913c0.312,-0.28 0.535,-0.816 1.053,-0.62Zm1.147,0.27c-0.115,-0.102 -0.074,-0.406 -0.02,-0.671c0.038,-0.188 0.097,-0.38 0.169,-0.558c0.537,-1.312 1.539,0.176 0.52,1.171c-0.198,0.193 -0.554,0.16 -0.669,0.058Zm-2.694,-0.036c-0.115,0.102 -0.466,0.107 -0.685,-0.022c-1.227,-0.724 -0.017,-2.483 0.52,-1.171c0.072,0.178 0.131,0.37 0.169,0.558c0.054,0.265 0.111,0.533 -0.004,0.635Zm2.253,-2.788c0.526,0.313 0.455,1.263 0.249,1.734c-0.134,0.308 -0.429,0.641 -0.798,0.417c-0.371,-0.224 -0.218,-1.459 -0.075,-1.812c0.093,-0.231 0.358,-0.498 0.624,-0.339Zm-1.534,-0.022c0.624,0.232 0.736,2.609 -0.203,2.193c-0.543,-0.24 -0.555,-1.228 -0.401,-1.693c0.083,-0.251 0.28,-0.621 0.604,-0.5Z"/>
            <svg>
        </div>
        <div class="wolves-status-icon" data-attribute="pack" data-value="\${packSpread}">
            <svg viewBox="-1 -1 11 11">
                <path d="M4.268,2.212c-0.163,0.031 -0.32,0.077 -0.469,0.138c-0.069,0.036 -0.155,0.026 -0.212,-0.025c-0.058,-0.051 -0.073,-0.13 -0.037,-0.196c0.32,-0.621 0.828,-1.565 1.083,-2.039c0.03,-0.055 0.091,-0.09 0.157,-0.09c0.067,0 0.128,0.035 0.158,0.09c0.255,0.474 0.763,1.418 1.095,2.033c0.037,0.071 0.021,0.156 -0.04,0.21c-0.061,0.055 -0.154,0.066 -0.228,0.027c-0.146,-0.063 -0.301,-0.11 -0.462,-0.143l-0,3.8c0,0.056 0.039,0.104 0.094,0.116c0.054,0.013 0.11,-0.014 0.134,-0.065c0.493,-1.026 1.068,-1.859 1.805,-2.411c0.006,-0.005 0.012,-0.009 0.018,-0.013c-0.14,-0.103 -0.292,-0.191 -0.451,-0.258c-0.07,-0.024 -0.115,-0.088 -0.11,-0.157c0.005,-0.069 0.058,-0.127 0.131,-0.142c0.706,-0.145 1.835,-0.378 2.42,-0.499c0.072,-0.015 0.147,0.009 0.193,0.062c0.047,0.054 0.057,0.126 0.027,0.189c-0.248,0.507 -0.725,1.486 -1.032,2.094c-0.029,0.059 -0.096,0.093 -0.166,0.084c-0.069,-0.009 -0.124,-0.06 -0.134,-0.124c-0.045,-0.174 -0.116,-0.344 -0.209,-0.506c-0.01,0.009 -0.02,0.017 -0.031,0.025c-1.304,0.977 -2.174,2.911 -2.174,5.188l-2.044,0c-0,-2.396 -0.932,-4.217 -2.192,-5.146c-0.018,-0.013 -0.034,-0.027 -0.049,-0.041c-0.085,0.154 -0.152,0.316 -0.194,0.48c-0.01,0.064 -0.065,0.115 -0.134,0.124c-0.07,0.009 -0.137,-0.025 -0.166,-0.084c-0.307,-0.608 -0.784,-1.587 -1.032,-2.094c-0.03,-0.063 -0.02,-0.135 0.026,-0.189c0.047,-0.053 0.122,-0.077 0.193,-0.062c0.586,0.121 1.715,0.354 2.421,0.499c0.073,0.015 0.126,0.073 0.131,0.142c0.005,0.069 -0.04,0.133 -0.11,0.157c-0.17,0.072 -0.331,0.167 -0.48,0.279c0.015,0.009 0.03,0.018 0.044,0.029c0.732,0.54 1.297,1.37 1.797,2.432c0.024,0.051 0.08,0.078 0.135,0.066c0.055,-0.013 0.093,-0.061 0.093,-0.117c0.001,-1.191 0.001,-3.863 0.001,-3.863Z"/>
            <svg>
        </div>
        <div class="wolves-status-icon" data-attribute="howl" data-value="\${howlRange}">
            <svg viewBox="-1 -1 11 11">
                <path d="M6.286,0.198c0.348,-0.285 0.855,-0.26 1.174,0.057c1.244,1.245 1.992,2.834 1.992,4.564c0,1.696 -0.718,3.255 -1.919,4.486c-0.353,0.363 -0.925,0.395 -1.316,0.075c-0.423,-0.34 -0.946,-0.768 -1.254,-1.019c-0.065,-0.053 -0.103,-0.132 -0.105,-0.215c-0.003,-0.083 0.032,-0.164 0.094,-0.22c0.895,-0.828 1.438,-1.916 1.438,-3.107c-0,-1.212 -0.563,-2.317 -1.486,-3.152c-0.049,-0.044 -0.077,-0.108 -0.076,-0.174c0.001,-0.067 0.032,-0.129 0.083,-0.171c0.314,-0.258 0.92,-0.753 1.375,-1.124Zm-4.336,5.91c0.418,-0.33 0.677,-0.785 0.677,-1.289c-0,-0.503 -0.259,-0.959 -0.677,-1.289l1.602,-1.264c0.829,0.653 1.342,1.557 1.342,2.553c-0,0.997 -0.513,1.9 -1.342,2.554l-1.602,-1.265Zm-1.059,-2.045c0.309,0.194 0.501,0.461 0.501,0.756c0,0.296 -0.192,0.563 -0.501,0.757l-0.722,-0.452c-0.116,-0.072 -0.169,-0.17 -0.169,-0.289c-0,-0.12 0.053,-0.248 0.169,-0.32c0.336,-0.21 0.722,-0.452 0.722,-0.452Z"/>
            <svg>
        </div>
        <div class="wolves-status-icon" data-attribute="terrain" data-value="\${terrainTokens}"></div>
        <div class="wolves-status-icon" data-attribute="action" data-value="\${actionTokens}"></div>
     </div>`;
const jstpl_log_icon =
    `<span class="wolves-log-icon-\${iconType}"
        data-owner="\${owner}" data-kind="\${kind}">
    </span>`
</script>  

{OVERALL_GAME_FOOTER}
