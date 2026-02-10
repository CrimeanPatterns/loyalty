<?php

namespace CPNRV3;

class Station
{
    /**
     * @var string
     */
    public $StationCode = null;

    /**
     * @var string
     */
    public $StationName = null;

    /**
     * @param string $StationCode
     * @param string $StationName
     */
    public function __construct($StationCode, $StationName)
    {
        $this->StationCode = $StationCode;
        $this->StationName = $StationName;
    }
}
