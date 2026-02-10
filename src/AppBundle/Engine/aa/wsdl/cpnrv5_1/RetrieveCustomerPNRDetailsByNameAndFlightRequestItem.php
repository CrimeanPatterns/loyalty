<?php

namespace CPNRV5_1;

class RetrieveCustomerPNRDetailsByNameAndFlightRequestItem
{
    /**
     * @var string
     */
    public $PassengerFirstName = null;

    /**
     * @var string
     */
    public $PassengerLastName = null;

    /**
     * @var bool
     */
    public $ExactFirstNameMatch = null;

    /**
     * @var string
     */
    public $CarrierCode = null;

    /**
     * @var string
     */
    public $FlightNumber = null;

    /**
     * @var date
     */
    public $DepartureDate = null;

    /**
     * @var string
     */
    public $DepartureCityCode = null;

    /**
     * @var string
     */
    public $ArrivalCityCode = null;

    /**
     * @param string $PassengerFirstName
     * @param string $PassengerLastName
     * @param bool $ExactFirstNameMatch
     * @param string $CarrierCode
     * @param string $FlightNumber
     * @param date $DepartureDate
     * @param string $DepartureCityCode
     * @param string $ArrivalCityCode
     */
    public function __construct($PassengerFirstName, $PassengerLastName, $ExactFirstNameMatch, $CarrierCode, $FlightNumber, $DepartureDate, $DepartureCityCode, $ArrivalCityCode)
    {
        $this->PassengerFirstName = $PassengerFirstName;
        $this->PassengerLastName = $PassengerLastName;
        $this->ExactFirstNameMatch = $ExactFirstNameMatch;
        $this->CarrierCode = $CarrierCode;
        $this->FlightNumber = $FlightNumber;
        $this->DepartureDate = $DepartureDate;
        $this->DepartureCityCode = $DepartureCityCode;
        $this->ArrivalCityCode = $ArrivalCityCode;
    }
}
