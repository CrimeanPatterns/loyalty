<?php

namespace CPNRV5_1;

class ServiceInfoType
{
    /**
     * @var date
     */
    public $BuildDate = null;

    /**
     * @param date $BuildDate
     */
    public function __construct($BuildDate)
    {
        $this->BuildDate = $BuildDate;
    }
}
