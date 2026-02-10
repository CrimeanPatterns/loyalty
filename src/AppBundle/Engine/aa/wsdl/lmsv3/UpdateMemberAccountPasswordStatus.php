<?php

class UpdateMemberAccountPasswordStatus
{
    /**
     * @var ProviderSystemInfo[]
     */
    public $ProviderSystemInfo = null;

    /**
     * @var ResponseStatusCodeEnum
     */
    public $Code = null;

    /**
     * @var ResponseStatusMessageEnum
     */
    public $Message = null;

    /**
     * @param ProviderSystemInfo[] $ProviderSystemInfo
     * @param ResponseStatusCodeEnum $Code
     * @param ResponseStatusMessageEnum $Message
     */
    public function __construct($ProviderSystemInfo, $Code, $Message)
    {
        $this->ProviderSystemInfo = $ProviderSystemInfo;
        $this->Code = $Code;
        $this->Message = $Message;
    }
}
