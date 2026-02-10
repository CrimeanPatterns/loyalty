<?php

namespace LMIV6;

class ResponseHeaderType
{
    /**
     * @var ListResponseHdrStatusType
     */
    public $ResponseStatus = null;

    /**
     * @var ServiceInfoType
     */
    public $ServiceInfo = null;

    /**
     * @param ListResponseHdrStatusType $ResponseStatus
     * @param ServiceInfoType $ServiceInfo
     */
    public function __construct($ResponseStatus, $ServiceInfo)
    {
        $this->ResponseStatus = $ResponseStatus;
        $this->ServiceInfo = $ServiceInfo;
    }
}
