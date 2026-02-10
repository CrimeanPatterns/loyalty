<?php

namespace LMRI2;

include_once 'ecdbAbstractResponse.php';

class customerReservationSummary extends ecdbAbstractResponse
{
    /**
     * @var string
     */
    public $arrivalCityCode = null;

    /**
     * @var string
     */
    public $departingCityCode = null;

    /**
     * @var dateTime
     */
    public $departureDate = null;

    /**
     * @var dateTime
     */
    public $PNRCreationDate = null;

    /**
     * @var string
     */
    public $recordLocator = null;

    /**
     * @var string
     */
    public $reservationStatus = null;

    /**
     * @var string
     */
    public $reservationStatusDate = null;
}
