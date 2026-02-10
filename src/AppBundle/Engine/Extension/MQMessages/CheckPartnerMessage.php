<?php
/**
 * Created by PhpStorm.
 * User: APuzakov
 * Date: 07.07.16
 * Time: 17:29
 */

namespace AppBundle\Extension\MQMessages;

use AppBundle\Document\MethodMap;
use JMS\Serializer\Annotation\Type;

class CheckPartnerMessage
{
    /**
     * @var string
     * @Type("string")
     */
    private $id;

    /**
     * @var string
     * @Type("string")
     */
    private $method;

    /**
     * @var string
     * @Type("string")
     */
    private $partner;

    /**
     * @var integer
     * @Type("integer")
     */
    private $priority;

    /**
     * @param string $methodKey - see CheckAccount::METHOD_KEY and other, should be in MethodMap::KEY_TO_CLASS
     */
    public function __construct(?string $id, string $methodKey, ?string $partner, ?int $priority)
    {
        if (!array_key_exists($methodKey, MethodMap::KEY_TO_CLASS)) {
            throw new \Exception("Unknown method key: $methodKey");
        }

        $this->id = $id;
        $this->method = $methodKey;
        $this->partner = $partner;
        $this->priority = $priority;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getPartner()
    {
        return $this->partner;
    }

    /**
     * @param string $partner
     * @return $this
     */
    public function setPartner($partner)
    {
        $this->partner = $partner;

        return $this;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param int $priority
     * @return $this
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;

        return $this;
    }

}