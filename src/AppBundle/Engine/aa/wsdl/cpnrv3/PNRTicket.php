<?php

namespace CPNRV3;

class PNRTicket
{
    /**
     * @var int
     */
    public $TicketSequenceIdentifier = null;

    /**
     * @var string
     */
    public $TicketStatusCode = null;

    /**
     * @var date
     */
    public $TicketStatusDate = null;

    /**
     * @var string
     */
    public $PNRTicketText = null;

    /**
     * @param int $TicketSequenceIdentifier
     * @param string $TicketStatusCode
     * @param date $TicketStatusDate
     * @param string $PNRTicketText
     */
    public function __construct($TicketSequenceIdentifier, $TicketStatusCode, $TicketStatusDate, $PNRTicketText)
    {
        $this->TicketSequenceIdentifier = $TicketSequenceIdentifier;
        $this->TicketStatusCode = $TicketStatusCode;
        $this->TicketStatusDate = $TicketStatusDate;
        $this->PNRTicketText = $PNRTicketText;
    }
}
