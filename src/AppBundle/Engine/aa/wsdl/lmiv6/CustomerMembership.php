<?php

namespace LMIV6;

class CustomerMembership
{
    /**
     * @var string[]
     */
    public $MembershipProgram = null;

    /**
     * @param string[] $MembershipProgram
     */
    public function __construct($MembershipProgram)
    {
        $this->MembershipProgram = $MembershipProgram;
    }
}
