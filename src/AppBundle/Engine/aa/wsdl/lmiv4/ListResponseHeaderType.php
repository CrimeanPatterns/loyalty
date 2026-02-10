<?php

namespace LMIV4;

include_once 'ResponseHeaderType.php';

class ListResponseHeaderType extends ResponseHeaderType
{
    /**
     * @param ListResponseHdrStatusType $ResponseStatus
     * @param ServiceInfoType $ServiceInfo
     */
    public function __construct($ResponseStatus, $ServiceInfo)
    {
        parent::__construct($ResponseStatus, $ServiceInfo);
    }
}
