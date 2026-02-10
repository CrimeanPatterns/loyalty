<?php

namespace LMIV3;

class AttributedURI
{
    /**
     * @var anyURI
     */
    public $_ = null;

    /**
     * @var ID
     */
    public $Id = null;

    /**
     * @param anyURI $_
     * @param ID $Id
     */
    public function __construct($_, $Id)
    {
        $this->_ = $_;
        $this->Id = $Id;
    }
}
