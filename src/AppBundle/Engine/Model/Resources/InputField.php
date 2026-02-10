<?php
/**
 * Created by PhpStorm.
 * User: APuzakov
 * Date: 13.04.16
 * Time: 15:21
 */

namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

/**
 * Generated resource DTO for 'InputField'.
 */
class InputField
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
    private $value;

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param $code
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;

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
     * @return $this
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }



}