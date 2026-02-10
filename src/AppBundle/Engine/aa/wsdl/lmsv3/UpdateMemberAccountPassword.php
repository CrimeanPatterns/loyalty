<?php

class UpdateMemberAccountPassword
{
    /**
     * @var string
     */
    public $in = null;

    /**
     * @param string $in
     */
    public function __construct($in)
    {
        $this->in = $in;
    }
}
