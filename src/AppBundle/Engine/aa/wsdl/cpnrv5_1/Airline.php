<?php

namespace CPNRV5_1;

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
