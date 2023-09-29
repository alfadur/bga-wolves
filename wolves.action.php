<?php
/**
 *------
 * BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
 * Wolves implementation : © <Your name here> <Your email address here>
 *
 * This code has been produced on the BGA studio platform for use on https://boardgamearena.com.
 * See http://en.doc.boardgamearena.com/Studio for more information.
 * -----
 * 
 * wolves.action.php
 *
 * Wolves main action entry point
 *
 *
 * In this file, you are describing all the methods that can be called from your
 * user interface logic (javascript).
 *       
 * If you define a method "myAction" here, then you can call it from your javascript code with:
 * this.ajaxcall( "/wolves/wolves/myAction.html", ...)
 *
 */
  
class action_wolves extends APP_GameAction {
    // Constructor: please do not modify
    public function __default(): void {
        if (self::isArg('notifwindow')) {
            $this->view = 'common_notifwindow';
            $this->viewArgs['table'] = self::getArg('table', AT_posint, true);
        } else {
            $this->view = 'wolves_wolves';
            self::trace('Complete reinitialization of board game');
        }
    }

    function draftPlace(): void {
        self::setAjaxMode();
        $x = self::getArg('x', AT_int, true);
        $y = self::getArg('y', AT_int, true);
        $this->game->draftPlace($x, $y);
        self::ajaxResponse();
    }

    function selectAction(): void {
        self::setAjaxMode();
        $action = self::getArg('actionId', AT_int, true);
        $bonusTokens = self::getArg('terrainTokens', AT_int, false) ?? 0;
        $forceTerrain = self::getArg('forceTerrain', AT_int, false);

        $tilesString = self::getArg('tiles', AT_numberlist, true);
        $tiles = strlen($tilesString) > 0 ? explode(',', $tilesString) : [];

        $this->game->selectAction($action,  $tiles, $bonusTokens, $forceTerrain);
        self::ajaxResponse();
    }

    function howl(): void {
        self::setAjaxMode();
        $wolfId = self::getArg('wolfId', AT_int, true);
        $x = self::getArg('x', AT_int, true);
        $y = self::getArg('y', AT_int, true);
        $this->game->howl($wolfId, $x, $y);
        self::ajaxResponse();
    }

    function move(): void {
        self::setAjaxMode();
        $wolfId = self::getArg('wolfId', AT_int, true);
        $steps = explode(',', self::getArg('steps', AT_numberlist, true));
        $this->game->move($wolfId, $steps);
        self::ajaxResponse();
    }

    function displace(): void {
        self::setAjaxMode();
        $steps = explode(',', self::getArg('steps', AT_numberlist, true));
        $this->game->displace($steps);
        self::ajaxResponse();
    }

    function den(): void {
        self::setAjaxMode();
        $wolfId = self::getArg('wolfId', AT_int, true);
        $direction = self::getArg('direction', AT_int, false);
        $denType = self::getArg('denType', AT_int, true);
        $this->game->placeDen($wolfId, $direction, $denType);
        self::ajaxResponse();
    }

    function lair(): void {
        self::setAjaxMode();
        $wolfId = self::getArg('wolfId', AT_int, true);
        $direction = self::getArg('direction', AT_int, false);
        $this->game->placeLair($wolfId, $direction);
        self::ajaxResponse();
    }

    function dominate(): void {
        self::setAjaxMode();
        $wolfId = self::getArg('wolfId', AT_int, true);
        $targetId = self::getArg('targetId', AT_int, true);
        $denType = self::getArg('denType', AT_int, true);
        $steps = explode(',', self::getArg('steps', AT_numberlist, true));
        $this->game->dominate($wolfId, $steps, $targetId, $denType);
        self::ajaxResponse();
    }

    function extraTurn(){
        self::setAjaxMode();
        $this->game->extraTurn();
        self::ajaxResponse();
    }

    function endTurn(){
        self::setAjaxMode();
        $this->game->endTurn();
        self::ajaxResponse();
    }

    function undo(){
        self::setAjaxMode();
        $this->game->undo();
        self::ajaxResponse();
    }

    function skip() {
        self::setAjaxMode();
        $this->game->skip();
        self::ajaxResponse();
    }
}
  

