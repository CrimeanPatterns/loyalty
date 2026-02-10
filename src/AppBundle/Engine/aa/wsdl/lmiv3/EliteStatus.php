<?php

namespace LMIV3;

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
     * @param string $EliteStatusCode
     * @param string $EliteStatusDescription
     */
    public function __construct($EliteStatusCode, $EliteStatusDescription)
    {
        $this->EliteStatusCode = $EliteStatusCode;
        $this->EliteStatusDescription = $EliteStatusDescription;
    }
}
