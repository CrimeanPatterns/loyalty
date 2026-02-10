<?php

namespace LMIV6;

class ServiceInfoType
{
    /**
     * @var string
     */
    public $ServiceName = null;

    /**
     * @var string
     */
    public $ServiceInstance = null;

    /**
     * @var string
     */
    public $WSDLVersion = null;

    /**
     * @var string
     */
    public $ImplematationVersion = null;

    /**
     * @var date
     */
    public $BuildDate = null;

    /**
     * @param string $ServiceName
     * @param string $ServiceInstance
     * @param string $WSDLVersion
     * @param string $ImplematationVersion
     * @param date $BuildDate
     */
    public function __construct($ServiceName, $ServiceInstance, $WSDLVersion, $ImplematationVersion, $BuildDate)
    {
        $this->ServiceName = $ServiceName;
        $this->ServiceInstance = $ServiceInstance;
        $this->WSDLVersion = $WSDLVersion;
        $this->ImplematationVersion = $ImplematationVersion;
        $this->BuildDate = $BuildDate;
    }
}
