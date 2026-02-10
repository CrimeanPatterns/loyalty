<?php

namespace CPNRV3;

class FaultType
{
    /**
     * @var string
     */
    public $ErrorCd = null;

    /**
     * @var ErrorTypeEnum
     */
    public $ErrorType = null;

    /**
     * @var string
     */
    public $ErrorMessage = null;

    /**
     * @param string $ErrorCd
     * @param ErrorTypeEnum $ErrorType
     * @param string $ErrorMessage
     */
    public function __construct($ErrorCd, $ErrorType, $ErrorMessage)
    {
        $this->ErrorCd = $ErrorCd;
        $this->ErrorType = $ErrorType;
        $this->ErrorMessage = $ErrorMessage;
    }
}
