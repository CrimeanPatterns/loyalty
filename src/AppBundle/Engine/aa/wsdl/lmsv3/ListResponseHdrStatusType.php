<?php

include_once 'ResponseStatusType.php';

class ListResponseHdrStatusType extends ResponseStatusType
{
    /**
     * @var string[]
     */
    public $Info = null;

    /**
     * @param int $Code
     * @param string $Message
     * @param string[] $Info
     */
    public function __construct($Code, $Message, $Info)
    {
        parent::__construct($Code, $Message);
        $this->Info = $Info;
    }
}
