<?php

class ProviderSystemInfo
{
    /**
     * @var string
     */
    public $ProviderSystemID = null;

    /**
     * @var ProviderSystemErrorTypeEnum
     */
    public $Type = null;

    /**
     * @var string
     */
    public $Code = null;

    /**
     * @var string
     */
    public $Message = null;

    /**
     * @param string $ProviderSystemID
     * @param ProviderSystemErrorTypeEnum $Type
     * @param string $Code
     * @param string $Message
     */
    public function __construct($ProviderSystemID, $Type, $Code, $Message)
    {
        $this->ProviderSystemID = $ProviderSystemID;
        $this->Type = $Type;
        $this->Code = $Code;
        $this->Message = $Message;
    }
}
