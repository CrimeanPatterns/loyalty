<?php

namespace LMRI2;

include_once 'requestHeader.php';

class ecdbAbstractRequest extends requestHeader
{
    /**
     * @var int
     */
    public $asyncProcessMaxThreadCount = null;

    /**
     * @var int
     */
    public $asyncTimeoutValue = null;

    /**
     * @var string
     */
    public $auditId = null;

    /**
     * @var string
     */
    public $authorizationId = null;

    /**
     * @var string
     */
    public $authorizationPassword = null;

    /**
     * @var string
     */
    public $clientHostName = null;

    /**
     * @var string
     */
    public $clientIPAddress = null;

    /**
     * @var string
     */
    public $clientName = null;

    /**
     * @var string
     */
    public $clientRelease = null;

    /**
     * @var string
     */
    public $clientServiceName = null;

    /**
     * @var string
     */
    public $dutyCode = null;

    /**
     * @var int
     */
    public $maxDurationOfThreadCountAtMax = null;

    /**
     * @var string
     */
    public $partitionCode = null;

    /**
     * @var bool
     */
    public $performAsyncProcess = null;

    /**
     * @var string
     */
    public $prNumber = null;

    /**
     * @var string
     */
    public $resAuthorizationId = null;

    /**
     * @var string
     */
    public $resAuthorizationIdSuffix = null;

    /**
     * @var string
     */
    public $resAuthorizationPassword = null;

    /**
     * @var resEnvironment
     */
    public $resEnvironment = null;
}
