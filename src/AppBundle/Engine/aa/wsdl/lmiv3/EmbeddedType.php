<?php

namespace LMIV3;

class EmbeddedType
{
    /**
     * @var string
     */
    public $any = null;

    /**
     * @var anyURI
     */
    public $ValueType = null;

    /**
     * @param string $any
     * @param anyURI $ValueType
     */
    public function __construct($any, $ValueType)
    {
        $this->any = $any;
        $this->ValueType = $ValueType;
    }
}
