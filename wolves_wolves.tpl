{OVERALL_GAME_HEADER}
<div id="wolves-land">
<!-- BEGIN region -->
    <div id="wolves-region-{ID}" data-tile="{N}" class="wolves-region {ROTATE}" style="left: {CX}px; top: {CY}px"></div>
<!-- END region -->
<!-- BEGIN hex -->
    <div id="wolves-hex-{X}-{Y}" class="wolves-hex wolves-hex-{TYPE}" style="left: {CX}px; top: {CY}px"></div>
<!-- END hex -->
</div>
<div id="player-board">
    <div id="player-tiles">
        <!-- BEGIN tile -->
            <span id="player-tile-{INDEX}" style="margin-inline:0.5em;" class="player-tile" data-terrain="{TYPE}"></span>
        <!-- END tile -->
    </div>
</div>
<script type="text/javascript">
const jstpl_hex_content = `<div id="wolves-piece-\${id}" class="wolves-hex-item wolves-piece-\${kind} \${locationClass}" style="background-color: #\${color}"></div>`
</script>  

{OVERALL_GAME_FOOTER}
