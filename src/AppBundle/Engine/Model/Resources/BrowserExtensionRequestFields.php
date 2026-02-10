<?php

namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Accessor;
use JMS\Serializer\Annotation\Type;
use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

trait BrowserExtensionRequestFields
{

    /**
     * @MongoDB\Field
     * @Type("boolean")
     * @Accessor(setter="deserializeBrowserExtensionAllowed")
     */
    private bool $browserExtensionAllowed = false;
    /**
     * user account identifier, used to link browser extension session with user account
     * @MongoDB\Field
     * @Type("string")
     */
    private ?string $loginId = null;
    /**
     * @MongoDB\Field
     * @Type("boolean")
     */
    private ?bool $browserExtensionIsMobile = null;
    /**
     * @MongoDB\Field
     * @Type("string")
     */
    private ?string $browserExtensionSessionId = null;

    public function isBrowserExtensionAllowed(): bool
    {
        return $this->browserExtensionAllowed;
    }

    public function setBrowserExtensionAllowed(bool $browserExtensionAllowed): self
    {
        $this->browserExtensionAllowed = $browserExtensionAllowed;

        return $this;
    }

    /** convert null to false
     * @internal
     * */
    public function deserializeBrowserExtensionAllowed(?bool $value)
    {
        $this->browserExtensionAllowed = $value === true;
    }

    public function getBrowserExtensionSessionId(): ?string
    {
        return $this->browserExtensionSessionId;
    }

    public function setBrowserExtensionSessionId(?string $browserExtensionSessionId): self
    {
        $this->browserExtensionSessionId = $browserExtensionSessionId;

        return $this;
    }

    public function isBrowserExtensionIsMobile(): ?bool
    {
        return $this->browserExtensionIsMobile;
    }

    public function setBrowserExtensionIsMobile(?bool $browserExtensionIsMobile): self
    {
        $this->browserExtensionIsMobile = $browserExtensionIsMobile;

        return $this;
    }

    public function getLoginId(): ?string
    {
        return $this->loginId;
    }

}