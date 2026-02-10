<?php

namespace LMIV3;

class SecurityTokenReferenceType
{
    /**
     * @var string
     */
    public $any = null;

    /**
     * @var ID
     */
    public $Id = null;

    /**
     * @var tUsage
     */
    public $Usage = null;

    /**
     * @param string $any
     * @param ID $Id
     * @param tUsage $Usage
     */
    public function __construct($any, $Id, $Usage)
    {
        $this->any = $any;
        $this->Id = $Id;
        $this->Usage = $Usage;
    }
}
