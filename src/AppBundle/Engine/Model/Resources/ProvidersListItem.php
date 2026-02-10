<?php
namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

/**
 * Generated resource DTO for 'ProvidersListItem'.
 */
class ProvidersListItem
{
    /**
     * @var string
     * @Type("string")
     */
    private $code;
        
    /**
     * @var string
     * @Type("string")
     */
    private $displayName;

    /**
     * @var integer
     * @Type("integer")
     */
    private $kind;

    public function __construct(string $code, string $displayName, int $kind)
    {
        $this->code = $code;
        $this->displayName = $displayName;
        $this->kind = $kind;
    }

    /**
     * @param string
     *
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;

        return $this;
    }

    /**
     * @param string
     *
     * @return $this
     */
    public function setDisplayname($displayName)
    {
        $this->displayName = $displayName;

        return $this;
    }

    /**
     * @param int $kind
     * @return $this
     */
    public function setKind($kind)
    {
        $this->kind = $kind;
        return $this;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @return string
     */
    public function getDisplayname()
    {
        return $this->displayName;
    }

    /**
     * @return int
     */
    public function getKind()
    {
        return $this->kind;
    }

}
