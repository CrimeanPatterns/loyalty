<?php

namespace LMRI2;

include_once 'ecdbAbstractResponse.php';

class customerReservationListResponse extends ecdbAbstractResponse
{
    /**
     * @var string
     */
    public $contextInfo = null;

    /**
     * @var customerReservationSummary[]
     */
    public $customerReservationSummaryTable = null;
}
