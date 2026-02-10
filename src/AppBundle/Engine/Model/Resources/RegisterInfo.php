<?php

namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

/**
 * Generated resource DTO for 'RegisterInfo'.
 */
class RegisterInfo
{
    /**
     * @var string
     * @Type("string")
     */
    private $key;

    /**
     * @var string
     * @Type("string")
     */
    private $value;

    public function __construct($key = null, $value = null)
    {
        $this->key = $key;
        $this->value = $value;
    }

    /**
     * @param string
     *
     * @return $this
     */
    public function setKey($key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * @param string
     *
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    public function clear()
    {
        $this->key = trim($this->key);
        $this->value = trim($this->value);
    }

    public function validate(): bool
    {
        return !(strlen($this->key) > 0 xor strlen($this->value) > 0);
    }
}
