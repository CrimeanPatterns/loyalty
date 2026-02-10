<?php

namespace CPNRV3;

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
