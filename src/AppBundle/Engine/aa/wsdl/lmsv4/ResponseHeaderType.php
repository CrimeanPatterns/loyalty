<?php

namespace LMSV4;

class ResponseHeaderType
{
    /**
     * @var ResponseStatusType
     */
    public $ResponseStatus = null;

    /**
     * @var ServiceInfoType
     */
    public $ServiceInfo = null;

    /**
     * @param ResponseStatusType $ResponseStatus
     * @param ServiceInfoType $ServiceInfo
     */
    public function __construct($ResponseStatus, $ServiceInfo)
    {
        $this->ResponseStatus = $ResponseStatus;
        $this->ServiceInfo = $ServiceInfo;
    }
}
