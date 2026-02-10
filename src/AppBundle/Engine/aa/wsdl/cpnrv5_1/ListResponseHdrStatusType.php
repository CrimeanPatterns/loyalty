<?php

namespace CPNRV5_1;

include_once 'ResponseStatusType.php';

class ListResponseHdrStatusType extends ResponseStatusType
{
    /**
     * @var string[]
     */
    public $Info = null;

    /**
     * @param string $Code
     * @param string $Message
     * @param string[] $Info
     */
    public function __construct($Code, $Message, $Info)
    {
        parent::__construct($Code, $Message);
        $this->Info = $Info;
    }
}
