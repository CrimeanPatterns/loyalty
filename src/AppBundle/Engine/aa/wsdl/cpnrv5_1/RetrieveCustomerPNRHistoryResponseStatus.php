<?php

namespace CPNRV5_1;

class RetrieveCustomerPNRHistoryResponseStatus
{
    /**
     * @var string
     */
    public $Code = null;

    /**
     * @var string
     */
    public $Message = null;

    /**
     * @param string $Code
     * @param string $Message
     */
    public function __construct($Code, $Message)
    {
        $this->Code = $Code;
        $this->Message = $Message;
    }
}
