<?php

namespace LMIV4;

class MemberActivitySummary
{
    /**
     * @var MemberSummaryDetail[]
     */
    public $MemberSummaryDetail = null;

    /**
     * @param MemberSummaryDetail[] $MemberSummaryDetail
     */
    public function __construct($MemberSummaryDetail)
    {
        $this->MemberSummaryDetail = $MemberSummaryDetail;
    }
}
