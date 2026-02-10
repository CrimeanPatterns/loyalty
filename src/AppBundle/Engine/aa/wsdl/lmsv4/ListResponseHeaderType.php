<?php

namespace LMSV4;

include_once 'ResponseHeaderType.php';

class ListResponseHeaderType extends ResponseHeaderType
{
    /**
     * @param ResponseStatusType $ResponseStatus
     * @param ServiceInfoType $ServiceInfo
     */
    public function __construct($ResponseStatus, $ServiceInfo)
    {
        parent::__construct($ResponseStatus, $ServiceInfo);
    }
}
