<?php

namespace LMIV6;

class MemberInformationResponseStatus
{
    /**
     * @var ProviderSystemInfo[]
     */
    public $ProviderSystemInfo = null;

    /**
     * @var string
     */
    public $Code = null;

    /**
     * @var string
     */
    public $Message = null;

    /**
     * @param ProviderSystemInfo[] $ProviderSystemInfo
     * @param string $Code
     * @param string $Message
     */
    public function __construct($ProviderSystemInfo, $Code, $Message)
    {
        $this->ProviderSystemInfo = $ProviderSystemInfo;
        $this->Code = $Code;
        $this->Message = $Message;
    }
}
