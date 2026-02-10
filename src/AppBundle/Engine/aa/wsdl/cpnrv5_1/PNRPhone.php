<?php

namespace CPNRV5_1;

class PNRPhone
{
    /**
     * @var int
     */
    public $PhoneSequenceId = null;

    /**
     * @var string
     */
    public $AirportCityCode = null;

    /**
     * @var string
     */
    public $PhoneNumber = null;

    /**
     * @var string
     */
    public $PhoneTypeCode = null;

    /**
     * @var int
     */
    public $CustomerPNRSequenceId = null;

    /**
     * @var int
     */
    public $DerivedPhoneNumber = null;

    /**
     * @param int $PhoneSequenceId
     * @param string $AirportCityCode
     * @param string $PhoneNumber
     * @param string $PhoneTypeCode
     * @param int $CustomerPNRSequenceId
     * @param int $DerivedPhoneNumber
     */
    public function __construct($PhoneSequenceId, $AirportCityCode, $PhoneNumber, $PhoneTypeCode, $CustomerPNRSequenceId, $DerivedPhoneNumber)
    {
        $this->PhoneSequenceId = $PhoneSequenceId;
        $this->AirportCityCode = $AirportCityCode;
        $this->PhoneNumber = $PhoneNumber;
        $this->PhoneTypeCode = $PhoneTypeCode;
        $this->CustomerPNRSequenceId = $CustomerPNRSequenceId;
        $this->DerivedPhoneNumber = $DerivedPhoneNumber;
    }
}
