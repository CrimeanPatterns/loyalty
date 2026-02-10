<?php

namespace CPNRV3;

class RetrieveCustomerPNRHistoryRequestItem
{
    /**
     * @var PNRIdentifier
     */
    public $PNRIdentifier = null;

    /**
     * @param PNRIdentifier $PNRIdentifier
     */
    public function __construct($PNRIdentifier)
    {
        $this->PNRIdentifier = $PNRIdentifier;
    }
}
