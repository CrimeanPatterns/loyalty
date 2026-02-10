<?php

namespace LMIV4;

class ListResponseHdrStatusType
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
     * @var string[]
     */
    public $Info = null;

    /**
     * @param ListResponseHdrStatusCodeType $Code
     * @param ListResponseHdrStatusMessageType $Message
     * @param string[] $Info
     */
    public function __construct($Code, $Message, $Info)
    {
        $this->Code = $Code;
        $this->Message = $Message;
        $this->Info = $Info;
    }
}
