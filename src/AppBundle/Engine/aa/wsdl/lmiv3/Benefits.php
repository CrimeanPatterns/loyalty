<?php

namespace LMIV3;

class Benefits
{
    /**
     * @var string
     */
    public $BenefitCode = null;

    /**
     * @var string
     */
    public $BenefitSourceId = null;

    /**
     * @var date
     */
    public $EffectiveDate = null;

    /**
     * @var date
     */
    public $ExpirationDate = null;

    /**
     * @var string
     */
    public $MergeCode = null;

    /**
     * @var string
     */
    public $BenefitDesc = null;

    /**
     * @param string $BenefitCode
     * @param string $BenefitSourceId
     * @param date $EffectiveDate
     * @param date $ExpirationDate
     * @param string $MergeCode
     * @param string $BenefitDesc
     */
    public function __construct($BenefitCode, $BenefitSourceId, $EffectiveDate, $ExpirationDate, $MergeCode, $BenefitDesc)
    {
        $this->BenefitCode = $BenefitCode;
        $this->BenefitSourceId = $BenefitSourceId;
        $this->EffectiveDate = $EffectiveDate;
        $this->ExpirationDate = $ExpirationDate;
        $this->MergeCode = $MergeCode;
        $this->BenefitDesc = $BenefitDesc;
    }
}
