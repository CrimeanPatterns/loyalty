<?php

namespace LMIV4;

class PartnerStratificationBenefits
{
    /**
     * @var string
     */
    public $PartnerId = null;

    /**
     * @var string
     */
    public $PartnerStratCode = null;

    /**
     * @var Benefits[]
     */
    public $Benefits = null;

    /**
     * @param string $PartnerId
     * @param string $PartnerStratCode
     * @param Benefits[] $Benefits
     */
    public function __construct($PartnerId, $PartnerStratCode, $Benefits)
    {
        $this->PartnerId = $PartnerId;
        $this->PartnerStratCode = $PartnerStratCode;
        $this->Benefits = $Benefits;
    }
}
