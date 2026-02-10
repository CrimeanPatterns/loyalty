<?php

namespace CPNRV3;

class Flight
{
    /**
     * @var Airline
     */
    public $Airline = null;

    /**
     * @var string
     */
    public $FlightNumber = null;

    /**
     * @param Airline $Airline
     * @param string $FlightNumber
     */
    public function __construct($Airline, $FlightNumber)
    {
        $this->Airline = $Airline;
        $this->FlightNumber = $FlightNumber;
    }
}
