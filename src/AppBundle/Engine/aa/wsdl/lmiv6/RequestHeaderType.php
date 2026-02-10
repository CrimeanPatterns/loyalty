<?php

namespace LMIV6;

class RequestHeaderType
{
    /**
     * @var string
     */
    public $ClientID = null;

    /**
     * @var string
     */
    public $WsdlVersion = null;

    /**
     * @var string
     */
    public $ServiceType = null;

    /**
     * @var string
     */
    public $ApplicationID = null;

    /**
     * @param string $ClientID
     * @param string $WsdlVersion
     * @param string $ServiceType
     * @param string $ApplicationID
     */
    public function __construct($ClientID, $WsdlVersion, $ServiceType, $ApplicationID)
    {
        $this->ClientID = $ClientID;
        $this->WsdlVersion = $WsdlVersion;
        $this->ServiceType = $ServiceType;
        $this->ApplicationID = $ApplicationID;
    }
}
