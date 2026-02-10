<?php

namespace LMIV3;

class PartnerProgramParticipation
{
    /**
     * @var PartnerStratification[]
     */
    public $PartnerStratification = null;

    /**
     * @var LoyaltyProgramPartner
     */
    public $LoyaltyProgramPartner = null;

    /**
     * @param PartnerStratification[] $PartnerStratification
     * @param LoyaltyProgramPartner $LoyaltyProgramPartner
     */
    public function __construct($PartnerStratification, $LoyaltyProgramPartner)
    {
        $this->PartnerStratification = $PartnerStratification;
        $this->LoyaltyProgramPartner = $LoyaltyProgramPartner;
    }
}
