<?php

namespace CPNRV3;

class RetrieveCustomerPNRHistoryResponseStatus
{
    /**
     * @var ResponseStatusCodeEnum
     */
    public $Code = null;

    /**
     * @var ResponseStatusMessageEnum
     */
    public $Message = null;

    /**
     * @param ResponseStatusCodeEnum $Code
     * @param ResponseStatusMessageEnum $Message
     */
    public function __construct($Code, $Message)
    {
        $this->Code = $Code;
        $this->Message = $Message;
    }
}
