<?php
/**
 * Created by PhpStorm.
 * User: puzakov
 * Date: 02/02/2017
 * Time: 14:29
 */

namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

class SubAccountHistory
{
    /**
     * @var string
     * @Type("string")
     */
    private $code;

    /**
     * @var HistoryRow[]
     * @Type("array<AppBundle\Model\Resources\HistoryRow>")
     */
    private $rows;

    public function __construct(string $code, ?array $rows)
    {
        $this->code = $code;
        $this->rows = $rows;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param string $code
     * @return $this
     */
    public function setCode($code)
    {
        $this->code = $code;
        return $this;
    }

    /**
     * @return HistoryRow[]
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * @param HistoryRow[] $rows
     * @return $this
     */
    public function setRows($rows)
    {
        $this->rows = $rows;
        return $this;
    }

}