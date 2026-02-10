<?php
/**
 * Created by PhpStorm.
 * User: puzakov
 * Date: 02/02/2017
 * Time: 12:21
 */

namespace AppBundle\Model\Resources;

use JMS\Serializer\Annotation\Type;

class History
{

    const HISTORY_COMPLETE = 'complete';
    const HISTORY_INCREMENTAL = 'incremental';
    const HISTORY_INCREMENTAL2 = 'incremental2';

    const HISTORY_RANGES = [
        self::HISTORY_COMPLETE,
        self::HISTORY_INCREMENTAL,
        self::HISTORY_INCREMENTAL2,
    ];

    /**
     * @var string
     * @Type("string")
     */
    private $range;

    /**
     * Base64 crypted HistoryState object
     * @var string
     * @Type("string")
     */
    private $state;

    /**
     * @var HistoryRow[]
     * @Type("array<AppBundle\Model\Resources\HistoryRow>")
     */
    private $rows;

    /**
     * @var SubAccountHistory[]
     * @Type("array<AppBundle\Model\Resources\SubAccountHistory>")
     */
    private $subAccounts;

    /**
     * @return string
     */
    public function getRange()
    {
        return $this->range;
    }

    /**
     * @param string $range
     * @return $this
     */
    public function setRange($range)
    {
        $this->range = $range;
        return $this;
    }

    /**
     * @return string
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param string $state
     * @return $this
     */
    public function setState($state)
    {
        $this->state = $state;
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

    /**
     * @return SubAccountHistory[]
     */
    public function getSubAccounts()
    {
        return $this->subAccounts;
    }

    /**
     * @param SubAccountHistory[] $subAccounts
     * @return $this
     */
    public function setSubAccounts($subAccounts)
    {
        $this->subAccounts = $subAccounts;
        return $this;
    }

}