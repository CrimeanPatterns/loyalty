<?php
/**
 * Created by PhpStorm.
 * User: puzakov
 * Date: 02/02/2017
 * Time: 12:22
 */

namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\HistoryState\StructureVersion1;
use JMS\Serializer\Annotation\SerializedName;
use JMS\Serializer\Annotation\Type;
use JMS\Serializer\Annotation\Discriminator;

/**
 * @Discriminator(field = "structureVersion",
 * map = {
 * 		1: "AppBundle\Model\Resources\HistoryState\StructureVersion1"
 * })
 */
abstract class HistoryState
{
    const ACTUAL_VERSION = StructureVersion1::class;
    const MINIMAL_VERSION_NUMBER = 1;

    /**
     * JMS Serializer Discriminator works only on field with name "type"
     * @var integer
     * @Type("integer")
     * @SerializedName("structureVersion")
     */
    protected $type;

    /**
     * @return int
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param int $type
     * @return $this
     */
    public function setType($type)
    {
        $this->type = $type;
        return $this;
    }

}