<?php

require_once __DIR__ . '/../pieology/functions.php';

class TAccountCheckerFazolis extends TAccountCheckerPieologyPunchhDotCom
{
    public $code = "fazolis";
    public $reCaptcha = true;
    public $seleniumAuth = true;
}
