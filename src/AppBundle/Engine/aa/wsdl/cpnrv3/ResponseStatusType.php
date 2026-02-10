<?php

namespace CPNRV3;

class ResponseStatusType
{
    /**
     * @var ListResponseHdrStatusCodeType
     */
    public $Code = null;

    /**
     * @var ListResponseHdrStatusMessageType
     */
    public $Message = null;

    /**
     * @param ListResponseHdrStatusCodeType $Code
     * @param ListResponseHdrStatusMessageType $Message
     */
    public function __construct($Code, $Message)
    {
        $this->Code = $Code;
        $this->Message = $Message;
    }
}
