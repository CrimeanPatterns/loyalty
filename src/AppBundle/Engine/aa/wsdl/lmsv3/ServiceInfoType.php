<?php

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
     * @param string $ServiceName
     * @param string $ServiceInstance
     * @param string $WSDLVersion
     */
    public function __construct($ServiceName, $ServiceInstance, $WSDLVersion)
    {
        $this->ServiceName = $ServiceName;
        $this->ServiceInstance = $ServiceInstance;
        $this->WSDLVersion = $WSDLVersion;
    }
}
