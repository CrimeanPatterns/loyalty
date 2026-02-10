<?php

namespace LMIV3;

class ReferenceType
{
    /**
     * @var anyURI
     */
    public $URI = null;

    /**
     * @var anyURI
     */
    public $ValueType = null;

    /**
     * @param anyURI $URI
     * @param anyURI $ValueType
     */
    public function __construct($URI, $ValueType)
    {
        $this->URI = $URI;
        $this->ValueType = $ValueType;
    }
}
