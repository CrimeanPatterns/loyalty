<?php

namespace LMIV4;

class FaultType
{
    /**
     * @var anonymous7
     */
    public $errorCd = null;

    /**
     * @var anonymous8
     */
    public $errorType = null;

    /**
     * @var anonymous9
     */
    public $errorMessage = null;

    /**
     * @param anonymous7 $errorCd
     * @param anonymous8 $errorType
     * @param anonymous9 $errorMessage
     */
    public function __construct($errorCd, $errorType, $errorMessage)
    {
        $this->errorCd = $errorCd;
        $this->errorType = $errorType;
        $this->errorMessage = $errorMessage;
    }
}
