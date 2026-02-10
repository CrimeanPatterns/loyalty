<?php

namespace LMIV3;

include_once 'AttributedString.php';

class PasswordString extends AttributedString
{
    /**
     * @var AttributedString
     */
    public $_ = null;

    /**
     * @var anyURI
     */
    public $Type = null;

    /**
     * @param string $_
     * @param ID $Id
     * @param AttributedString $_
     * @param anyURI $Type
     */
    public function __construct($_, $Id, $_1, $Type)
    {
        parent::__construct($_, $Id);
        $this->_ = $_;
        $this->Type = $Type;
    }
}
