<?php

namespace CPNRV3;

class PNRSpecialServiceRequest
{
    /**
     * @var int
     */
    public $SequenceIdentifier = null;

    /**
     * @var string
     */
    public $Code = null;

    /**
     * @var string
     */
    public $Text = null;

    /**
     * @var int
     */
    public $Quantity = null;

    /**
     * @param int $SequenceIdentifier
     * @param string $Code
     * @param string $Text
     * @param int $Quantity
     */
    public function __construct($SequenceIdentifier, $Code, $Text, $Quantity)
    {
        $this->SequenceIdentifier = $SequenceIdentifier;
        $this->Code = $Code;
        $this->Text = $Text;
        $this->Quantity = $Quantity;
    }
}
