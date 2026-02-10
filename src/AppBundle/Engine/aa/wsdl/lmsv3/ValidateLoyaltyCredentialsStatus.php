<?php

class ValidateLoyaltyCredentialsStatus
{
    /**
     * @var ProviderSystemInfo[]
     */
    public $ProviderSystemInfo = null;

    /**
     * @var int
     */
    public $Code = null;

    /**
     * @var string
     */
    public $Message = null;

    /**
     * @param ProviderSystemInfo[] $ProviderSystemInfo
     * @param int $Code
     * @param string $Message
     */
    public function __construct($ProviderSystemInfo, $Code, $Message)
    {
        $this->ProviderSystemInfo = $ProviderSystemInfo;
        $this->Code = $Code;
        $this->Message = $Message;
    }
}
