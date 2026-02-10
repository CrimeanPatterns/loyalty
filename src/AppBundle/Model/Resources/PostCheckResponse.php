<?php
namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\Interfaces\LoyaltyResponseInterface;
use JMS\Serializer\Annotation\Type;

class PostCheckResponse implements LoyaltyResponseInterface
{
    /**
     * @var string
     * @Type("string")
     */
    private $requestId;
    /**
     * @Type("string")
     */
    private ?string $browserExtensionSessionId = null;
    /**
     * @Type("string")
     */
    private ?string $browserExtensionConnectionToken = null;

    /**
     * @param string
     * @return $this
     */
    public function setRequestid($requestId)
    {
        $this->requestId = $requestId;
        return $this;
    }
            
    /**
     * @return string
     */
    public function getRequestid()
    {
        return $this->requestId;
    }

    public function getBrowserExtensionConnectionToken(): ?string
    {
        return $this->browserExtensionConnectionToken;
    }

    public function setBrowserExtensionConnectionToken(?string $browserExtensionConnectionToken): void
    {
        $this->browserExtensionConnectionToken = $browserExtensionConnectionToken;
    }

    public function getBrowserExtensionSessionId(): ?string
    {
        return $this->browserExtensionSessionId;
    }

    public function setBrowserExtensionSessionId(?string $browserExtensionSessionId): void
    {
        $this->browserExtensionSessionId = $browserExtensionSessionId;
    }
}
