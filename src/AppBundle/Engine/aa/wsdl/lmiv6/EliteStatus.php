<?php

namespace LMIV6;

class EliteStatus
{
    /**
     * @var string
     */
    public $EliteStatusCode = null;

    /**
     * @var string
     */
    public $EliteStatusDescription = null;

    /**
     * @var date
     */
    public $EliteProgressExpirationDate = null;

    /**
     * @var date
     */
    public $EliteStatusExpirationDate = null;

    /**
     * @var string
     */
    public $LifetimeVestedStatus = null;

    /**
     * @param string $EliteStatusCode
     * @param string $EliteStatusDescription
     * @param date $EliteProgressExpirationDate
     * @param date $EliteStatusExpirationDate
     * @param string $LifetimeVestedStatus
     */
    public function __construct($EliteStatusCode, $EliteStatusDescription, $EliteProgressExpirationDate, $EliteStatusExpirationDate, $LifetimeVestedStatus)
    {
        $this->EliteStatusCode = $EliteStatusCode;
        $this->EliteStatusDescription = $EliteStatusDescription;
        $this->EliteProgressExpirationDate = $EliteProgressExpirationDate;
        $this->EliteStatusExpirationDate = $EliteStatusExpirationDate;
        $this->LifetimeVestedStatus = $LifetimeVestedStatus;
    }
}
