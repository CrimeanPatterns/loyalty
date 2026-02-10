<?php

namespace LMIV4;

class MemberInformationRetrieveRequestItem
{
    /**
     * @var AAdvantageNumber
     */
    public $AAdvantageNumber = null;

    /**
     * @var AAdvantagePassword
     */
    public $AAdvantagePassword = null;

    /**
     * @param AAdvantageNumber $AAdvantageNumber
     * @param AAdvantagePassword $AAdvantagePassword
     */
    public function __construct($AAdvantageNumber, $AAdvantagePassword)
    {
        $this->AAdvantageNumber = $AAdvantageNumber;
        $this->AAdvantagePassword = $AAdvantagePassword;
    }
}
