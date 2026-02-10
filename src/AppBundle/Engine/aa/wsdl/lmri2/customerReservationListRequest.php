<?php

namespace LMRI2;

include_once 'ecdbAbstractRequest.php';

class customerReservationListRequest extends ecdbAbstractRequest
{
    /**
     * @var bool
     */
    public $bookedAacomOrAasegmentOnly = null;

    /**
     * @var string
     */
    public $clientCode = null;

    /**
     * @var string
     */
    public $loyaltyCompany = null;

    /**
     * @var string
     */
    public $loyaltyNumber = null;

    /**
     * @var dateTime
     */
    public $PNRCreationDate = null;

    /**
     * @var dateTime
     */
    public $pnrFromDate = null;

    /**
     * @var dateTime
     */
    public $pnrToDate = null;

    /**
     * @var string
     */
    public $recordLocator = null;

    /**
     * @var bool
     */
    public $retrieveLimitedChangePnr = null;

    /**
     * @var int
     */
    public $requestedListCount = null;
}
