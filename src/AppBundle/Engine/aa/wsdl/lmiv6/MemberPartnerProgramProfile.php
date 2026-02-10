<?php

namespace LMIV6;

class MemberPartnerProgramProfile
{
    /**
     * @var PartnerProgramParticipation
     */
    public $PartnerProgramParticipation = null;

    /**
     * @param PartnerProgramParticipation $PartnerProgramParticipation
     */
    public function __construct($PartnerProgramParticipation)
    {
        $this->PartnerProgramParticipation = $PartnerProgramParticipation;
    }
}
