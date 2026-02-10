<?php


namespace AppBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\EmbeddedDocument
 */
class RaAccountRegisterInfo
{

    /** @MongoDB\Id */
    private $id;

    /** @MongoDB\Field(type="string") */
    private $key;

    /** @MongoDB\Field(type="string") */
    private $value;

    public function __construct(string $key, string $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param string $key
     * @return RaAccountRegisterInfo
     */
    public function setKey(string $key)
    {
        $this->key = $key;
        return $this;
    }

    /**
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @param string $value
     * @return RaAccountRegisterInfo
     */
    public function setValue(string $value)
    {
        $this->value = $value;
        return $this;
    }

}