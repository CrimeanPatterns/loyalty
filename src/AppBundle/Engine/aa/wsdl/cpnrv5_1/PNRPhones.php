<?php

namespace CPNRV5_1;

class PNRPhones
{
    /**
     * @var PNRPhone[]
     */
    public $PNRPhone = null;

    /**
     * @param PNRPhone[] $PNRPhone
     */
    public function __construct($PNRPhone)
    {
        $this->PNRPhone = $PNRPhone;
    }
}
