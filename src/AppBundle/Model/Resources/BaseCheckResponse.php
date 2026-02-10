<?php
namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\Interfaces\LoyaltyResponseInterface;
use AwardWallet\Common\Itineraries\Itinerary;
use JMS\Serializer\Annotation\Type;

class BaseCheckResponse implements LoyaltyResponseInterface
{

    /**
     * @var string
     * @Type("string")
     */
    protected $requestId;

    /**
     * @var string
     * @Type("string")
     */
    protected $userData;

    /**
     * @var string
     * @Type("string")
     */
    protected $provider;

    /**
     * @var string
     * @Type("string")
     */
    protected $debugInfo;

    /**
     * @var integer
     * @Type("integer")
     */
    protected $state;

    /**
     * @var string
     * @Type("string")
     */
    protected $message;

    /**
     * @var string
     * @Type("string")
     */
    protected $errorReason;

    /**
     * @var \DateTime
     * @Type("DateTime<'Y-m-d\TH:i:sP'>")
     */
    protected $checkDate;

    /**
     * @var \DateTime
     * @Type("DateTime<'Y-m-d\TH:i:sP'>")
     */
    protected $requestDate;

    /**
     * @var \AwardWallet\Common\Itineraries\Itinerary[]
     * @Type("array<AwardWallet\Common\Itineraries\Itinerary>")
     */
    protected $itineraries;

    /**
     * @var string[]
     * @Type("array<string>")
     */
    protected $warnings;

    public function __construct(?string $requestId = null, ?int $state = null, ?string $userData = null, ?\DateTime $requestDate = null)
    {
        $this->requestId = $requestId;
        $this->state = $state;
        $this->userData = $userData;
        $this->requestDate = $requestDate;
    }

    /**
     * @param string
     *
     * @return $this
     */
    public function setRequestid($requestId)
    {
        $this->requestId = $requestId;

        return $this;
    }

    /**
     * @param string
     *
     * @return $this
     */
    public function setUserdata($userData)
    {
        $this->userData = $userData;

        return $this;
    }

    /**
     * @param string
     *
     * @return $this
     */
    public function setProvider($provider)
    {
        $this->provider = $provider;

        return $this;
    }

    /**
     * @param string
     *
     * @return $this
     */
    public function setDebuginfo($debugInfo)
    {
        $this->debugInfo = $debugInfo;

        return $this;
    }

    /**
     * @param string
     *
     * @return $this
     */
    public function addDebuginfo($debugInfo)
    {
        if (!empty($this->debugInfo)) {
            $this->debugInfo .= "\n" . $debugInfo;
        } else {
            $this->debugInfo = $debugInfo;
        }

        return $this;
    }

    /**
     * @param integer
     *
     * @return $this
     */
    public function setState($state)
    {
        $this->state = $state;

        return $this;
    }

    /**
     * @param string
     *
     * @return $this
     */
    public function setMessage($message)
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @param \DateTime
     *
     * @return $this
     */
    public function setCheckdate($checkDate)
    {
        $this->checkDate = $checkDate;

        return $this;
    }

    /**
     * @param \DateTime
     *
     * @return $this
     */
    public function setRequestdate($requestDate)
    {
        $this->requestDate = $requestDate;

        return $this;
    }

    /**
     * @param Itinerary[] $itineraries
     * @return $this
     */
    public function setItineraries($itineraries)
    {
        $this->itineraries = $itineraries;

        return $this;
    }

    /**
     * @param $errorReason
     * @return $this
     */
    public function setErrorreason($errorReason)
    {
        $this->errorReason = $errorReason;
        return $this;
    }

    /**
     * @return string
     */
    public function getErrorreason()
    {
        return $this->errorReason;
    }

    /**
     * @return string
     */
    public function getRequestId()
    {
        return $this->requestId;
    }

    /**
     * @return string
     */
    public function getUserdata()
    {
        return $this->userData;
    }

    /**
     * @return string
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * @return string
     */
    public function getDebuginfo()
    {
        return $this->debugInfo;
    }

    /**
     * @return integer
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return \DateTime
     */
    public function getCheckdate()
    {
        return $this->checkDate;
    }

    /**
     * @return \DateTime
     */

    public function getRequestdate()
    {
        return $this->requestDate;
    }

    /**
     * @return Itinerary[]
     */
    public function getItineraries()
    {
        return $this->itineraries;
    }

    /**
     * @return string[]
     */
    public function getWarnings(): ?array
    {
        return $this->warnings;
    }

    /**
     * @param string[] $warnings
     * @return $this
     */
    public function setWarnings(array $warnings)
    {
        $this->warnings = $warnings;
        return $this;
    }

    public function addWarning(string $warning) : void
    {
        if (!is_array($this->warnings)) {
            $this->warnings = [];
        }

        $this->warnings[] = $warning;
    }
}
