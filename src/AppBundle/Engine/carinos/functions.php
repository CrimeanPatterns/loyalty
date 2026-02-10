<?php

require_once __DIR__ . '/../california/functions.php';

class TAccountCheckerCarinos extends TAccountCheckerCalifornia
{
    /*
     * like as papaginos, burgerville, gordonb, genghisgrill, california, carinos, cosi, oberweis, restaurants, tortilla
     *
     * like as huhot, canes, whichwich, boloco
     *
     * like as freebirds, maxermas  // refs #16823
     */

    public $loginURL = "https://carinos.myguestaccount.com/guest/accountlogin";
    public $balanceURL = "https://carinos.myguestaccount.com/guest/account-balance";
    public $code = "carinos";
}
