<?php

namespace LMIV3;

class FaultType
{
    /**
     * @var anonymous24
     */
    public $errorCd = null;

    /**
     * @var anonymous25
     */
    public $errorType = null;

    /**
     * @var anonymous26
     */
    public $errorMessage = null;

    /**
     * @param anonymous24 $errorCd
     * @param anonymous25 $errorType
     * @param anonymous26 $errorMessage
     */
    public function __construct($errorCd, $errorType, $errorMessage)
    {
        $this->errorCd = $errorCd;
        $this->errorType = $errorType;
        $this->errorMessage = $errorMessage;
    }
}
