<?php

namespace LMIV3;

class ResponseStatusType
{
    /**
     * @var int
     */
    public $Code = null;

    /**
     * @var string
     */
    public $Message = null;

    /**
     * @param int $Code
     * @param string $Message
     */
    public function __construct($Code, $Message)
    {
        $this->Code = $Code;
        $this->Message = $Message;
    }
}
