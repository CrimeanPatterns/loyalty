<?php

namespace CPNRV5_1;

class SegmentStatus
{
    /**
     * @var string
     */
    public $SegmentStatusCode = null;

    /**
     * @param string $SegmentStatusCode
     */
    public function __construct($SegmentStatusCode)
    {
        $this->SegmentStatusCode = $SegmentStatusCode;
    }
}
