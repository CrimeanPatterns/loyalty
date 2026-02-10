<?php

namespace CPNRV5_1;

class PNRHistorySegmentFilter
{
    /**
     * @var string[]
     */
    public $SegmentFilterEventName = null;

    /**
     * @var dateTime
     */
    public $SegmentAfterTimestamp = null;

    /**
     * @var bool
     */
    public $IncludePNRPassenger = null;

    /**
     * @param string[] $SegmentFilterEventName
     * @param dateTime $SegmentAfterTimestamp
     * @param bool $IncludePNRPassenger
     */
    public function __construct($SegmentFilterEventName, $SegmentAfterTimestamp, $IncludePNRPassenger)
    {
        $this->SegmentFilterEventName = $SegmentFilterEventName;
        $this->SegmentAfterTimestamp = $SegmentAfterTimestamp;
        $this->IncludePNRPassenger = $IncludePNRPassenger;
    }
}
