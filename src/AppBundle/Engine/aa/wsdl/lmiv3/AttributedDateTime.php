<?php

namespace LMIV3;

class AttributedDateTime
{
    /**
     * @var string
     */
    public $_ = null;

    /**
     * @var ID
     */
    public $Id = null;

    /**
     * @param string $_
     * @param ID $Id
     */
    public function __construct($_, $Id)
    {
        $this->_ = $_;
        $this->Id = $Id;
    }
}
