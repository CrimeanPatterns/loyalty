<?php

namespace CPNRV3;

class Airline
{
    /**
     * @var string
     */
    public $AirlineCode = null;

    /**
     * @param string $AirlineCode
     */
    public function __construct($AirlineCode)
    {
        $this->AirlineCode = $AirlineCode;
    }
}
