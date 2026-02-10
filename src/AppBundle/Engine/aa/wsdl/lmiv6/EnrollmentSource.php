<?php

namespace LMIV6;

class EnrollmentSource
{
    /**
     * @var string
     */
    public $ProgramEnrollmentCode = null;

    /**
     * @var string
     */
    public $EnrollmentSourceDescription = null;

    /**
     * @param string $ProgramEnrollmentCode
     * @param string $EnrollmentSourceDescription
     */
    public function __construct($ProgramEnrollmentCode, $EnrollmentSourceDescription)
    {
        $this->ProgramEnrollmentCode = $ProgramEnrollmentCode;
        $this->EnrollmentSourceDescription = $EnrollmentSourceDescription;
    }
}
