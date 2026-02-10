<?php

require_once __DIR__ . '/../pieology/functions.php';

class TAccountCheckerCicis extends TAccountCheckerPieologyPunchhDotCom
{
    public $code = "cicispizza";
    public $reCaptcha = true;
}
