<?php

namespace LMIV3;

class UsernameTokenType
{
    /**
     * @var AttributedString
     */
    public $Username = null;

    /**
     * @var string
     */
    public $any = null;

    /**
     * @var ID
     */
    public $Id = null;

    /**
     * @param AttributedString $Username
     * @param string $any
     * @param ID $Id
     */
    public function __construct($Username, $any, $Id)
    {
        $this->Username = $Username;
        $this->any = $any;
        $this->Id = $Id;
    }
}
