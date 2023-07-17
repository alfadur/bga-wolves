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
        $action = self::getArg('action_id', AT_int, true);
        $bonusTokens = self::getArg('terrain_tokens', AT_int, false) ?? 0;

        $tiles = explode(',', self::getArg('tiles', AT_numberlist, false));

        print_r($tiles);

        $this->game->selectAction($action,  $tiles, $bonusTokens);
        self::ajaxResponse();
    }

    function howl(): void {
        self::setAjaxMode();
        $x = self::getArg('x', AT_int, true);
        $y = self::getArg('y', AT_int, true);
        $this->game->howl($x, $y);
        self::ajaxResponse();
    }

    function move(): void {
        self::setAjaxMode();
        $wolfId = self::getArg('wolfId', AT_int, true);
        $path = explode(',', self::getArg('path', AT_numberlist, true));
        $this->game->move($wolfX, $wolfY, $kind, $targetX, $targetY);
        self::ajaxResponse();
    }

    function displace(): void {
        self::setAjaxMode();
        $path = explode(',', self::getArg('path', AT_numberlist, true));
        $this->game->displace($path);
        self::ajaxResponse();
    }
}
  

