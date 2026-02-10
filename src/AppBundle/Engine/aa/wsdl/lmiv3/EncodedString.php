<?php

namespace LMIV3;

include_once 'AttributedString.php';

class EncodedString extends AttributedString
{
    /**
     * @var AttributedString
     */
    public $_ = null;

    /**
     * @var anyURI
     */
    public $EncodingType = null;

    /**
     * @param string $_
     * @param ID $Id
     * @param AttributedString $_
     * @param anyURI $EncodingType
     */
    public function __construct($_, $Id, $_1, $EncodingType)
    {
        parent::__construct($_, $Id);
        $this->_ = $_;
        $this->EncodingType = $EncodingType;
    }
}
