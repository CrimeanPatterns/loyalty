<?php

namespace LMIV3;

class TransformationParametersType
{
    /**
     * @var string
     */
    public $any = null;

    /**
     * @param string $any
     */
    public function __construct($any)
    {
        $this->any = $any;
    }
}
