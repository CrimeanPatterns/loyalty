<?php

namespace LMIV3;

include_once 'EncodedString.php';

class KeyIdentifierType extends EncodedString
{
    /**
     * @var EncodedString
     */
    public $_ = null;

    /**
     * @var anyURI
     */
    public $ValueType = null;

    /**
     * @param AttributedString $_
     * @param anyURI $EncodingType
     * @param EncodedString $_
     * @param anyURI $ValueType
     */
    public function __construct($_, $EncodingType, $_1, $ValueType)
    {
        parent::__construct($_, $EncodingType);
        $this->_ = $_;
        $this->ValueType = $ValueType;
    }
}
