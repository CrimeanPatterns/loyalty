<?php

namespace CPNRV5_1;

class FaultType
{
    /**
     * @var string
     */
    public $ErrorCd = null;

    /**
     * @var string
     */
    public $ErrorType = null;

    /**
     * @var string
     */
    public $ErrorMessage = null;

    /**
     * @param string $ErrorCd
     * @param string $ErrorType
     * @param string $ErrorMessage
     */
    public function __construct($ErrorCd, $ErrorType, $ErrorMessage)
    {
        $this->ErrorCd = $ErrorCd;
        $this->ErrorType = $ErrorType;
        $this->ErrorMessage = $ErrorMessage;
    }
}
