<?php

namespace LMIV3;

class LoyaltyMemberName
{
    /**
     * @var date
     */
    public $LoyaltyMemberNameChangeDate = null;

    /**
     * @var string
     */
    public $FirstName = null;

    /**
     * @var string
     */
    public $MiddleName = null;

    /**
     * @var string
     */
    public $LastName = null;

    /**
     * @var string
     */
    public $Prefix = null;

    /**
     * @var string
     */
    public $Suffix = null;

    /**
     * @param date $LoyaltyMemberNameChangeDate
     * @param string $FirstName
     * @param string $MiddleName
     * @param string $LastName
     * @param string $Prefix
     * @param string $Suffix
     */
    public function __construct($LoyaltyMemberNameChangeDate, $FirstName, $MiddleName, $LastName, $Prefix, $Suffix)
    {
        $this->LoyaltyMemberNameChangeDate = $LoyaltyMemberNameChangeDate;
        $this->FirstName = $FirstName;
        $this->MiddleName = $MiddleName;
        $this->LastName = $LastName;
        $this->Prefix = $Prefix;
        $this->Suffix = $Suffix;
    }
}
