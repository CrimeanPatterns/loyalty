<?php

namespace LMIV3;

class PartnerStratification
{
    /**
     * @var string
     */
    public $StratificationCode = null;

    /**
     * @var string
     */
    public $PartnerStratificationName = null;

    /**
     * @param string $StratificationCode
     * @param string $PartnerStratificationName
     */
    public function __construct($StratificationCode, $PartnerStratificationName)
    {
        $this->StratificationCode = $StratificationCode;
        $this->PartnerStratificationName = $PartnerStratificationName;
    }
}
