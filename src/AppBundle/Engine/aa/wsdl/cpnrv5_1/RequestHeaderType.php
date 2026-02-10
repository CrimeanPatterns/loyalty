<?php

namespace CPNRV5_1;

class RequestHeaderType
{
    /**
     * @var bool
     */
    public $IncludeInactivePNR = null;

    /**
     * @var string
     */
    public $transactionId = null;

    /**
     * @param bool $IncludeInactivePNR
     * @param string $transactionId
     */
    public function __construct($IncludeInactivePNR, $transactionId)
    {
        $this->IncludeInactivePNR = $IncludeInactivePNR;
        $this->transactionId = $transactionId;
    }
}
