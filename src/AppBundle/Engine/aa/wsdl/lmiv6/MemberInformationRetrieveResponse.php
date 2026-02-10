<?php

namespace LMIV6;

include_once 'ListResponseHeaderType.php';

class MemberInformationRetrieveResponse extends ListResponseHeaderType
{
    /**
     * @var MemberInformationRetrieveResult[]
     */
    public $MemberInformationRetrieveResult = null;

    /**
     * @param MemberInformationRetrieveResult[] $MemberInformationRetrieveResult
     */
    public function __construct($MemberInformationRetrieveResult)
    {
        parent::__construct();
        $this->MemberInformationRetrieveResult = $MemberInformationRetrieveResult;
    }
}
