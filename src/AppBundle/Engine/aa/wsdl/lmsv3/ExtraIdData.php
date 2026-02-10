<?php

class ExtraIdData
{
    /**
     * @var IdType
     */
    public $IdType = null;

    /**
     * @var string
     */
    public $IdValue = null;

    /**
     * @param IdType $IdType
     * @param string $IdValue
     */
    public function __construct($IdType, $IdValue)
    {
        $this->IdType = $IdType;
        $this->IdValue = $IdValue;
    }
}
