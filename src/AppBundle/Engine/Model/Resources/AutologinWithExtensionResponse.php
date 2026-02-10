<?php
namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\Interfaces\LoyaltyResponseInterface;
use JMS\Serializer\Annotation\Type;

class AutologinWithExtensionResponse implements LoyaltyResponseInterface
{
    /**
     * @Type("string")
     */
    private string $browserExtensionSessionId;
    /**
     * @Type("string")
     */
    private string $browserExtensionConnectionToken;

    public function getBrowserExtensionConnectionToken(): string
    {
        return $this->browserExtensionConnectionToken;
    }

    public function setBrowserExtensionConnectionToken(string $browserExtensionConnectionToken): void
    {
        $this->browserExtensionConnectionToken = $browserExtensionConnectionToken;
    }

    public function getBrowserExtensionSessionId(): string
    {
        return $this->browserExtensionSessionId;
    }

    public function setBrowserExtensionSessionId(string $browserExtensionSessionId): void
    {
        $this->browserExtensionSessionId = $browserExtensionSessionId;
    }
}
