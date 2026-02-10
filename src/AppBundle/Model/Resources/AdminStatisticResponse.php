<?php
namespace AppBundle\Model\Resources;

use AppBundle\Model\Resources\Interfaces\LoyaltyResponseInterface;
use JMS\Serializer\Annotation\Type;

class AdminStatisticResponse implements LoyaltyResponseInterface
{

    /**
     * @var array
     * @Type("array")
     */
    private $providers;
    /**
     * @var integer
     * @Type("integer")
     */
    private $uniqueUsers;
    /**
     * @var integer
     * @Type("integer")
     */
    private $totalAccounts;

    /**
     * @return array
     */
    public function getProviders()
    {
        return $this->providers;
    }

    /**
     * @param array $providers
     * @return $this
     */
    public function setProviders($providers)
    {
        $this->providers = $providers;
        return $this;
    }

    /**
     * @return int
     */
    public function getUniqueUsers()
    {
        return $this->uniqueUsers;
    }

    /**
     * @param int $uniqueUsers
     * @return $this
     */
    public function setUniqueUsers($uniqueUsers)
    {
        $this->uniqueUsers = $uniqueUsers;
        return $this;
    }

    /**
     * @return int
     */
    public function getTotalAccounts()
    {
        return $this->totalAccounts;
    }

    /**
     * @param int $totalAccounts
     * @return $this
     */
    public function setTotalAccounts($totalAccounts)
    {
        $this->totalAccounts = $totalAccounts;
        return $this;
    }

}
