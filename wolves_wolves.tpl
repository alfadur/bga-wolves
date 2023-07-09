{OVERALL_GAME_HEADER}
<div id="wolves-land">
<!-- BEGIN hex -->
    <div id="wolves-hex-{X}-{Y}" class="wolves-hex wolves-hex-{TYPE}" style="left: {CX}px; top: {CY}px"></div>
<!-- END hex -->
</div>
<div id="player-board">
    <div id="player-tiles">
        <!-- BEGIN tile -->
            <span id="player-tile-{INDEX}" style="margin-inline:0.5em;" class="player-tile" terrain="{TYPE}"></span>
        <!-- END tile -->
    </div>
    <div id="action-buttons">
        <button class="action-button">Move</button>
        <button class="action-button">Howl</button>
        <button class="action-button">Dominate</button>
        <button class="action-button">Den</button>
        <button class="action-button">Lair</button>
    </div>
</div>
<script type="text/javascript">
const jstpl_hex_content = `<div id="wolves-hex-\${x}-\${y}-item" class="wolves-hex-item" style="background-color: #\${color}"></div>`
</script>  

{OVERALL_GAME_FOOTER}
