<?php

namespace AppBundle\Extension;

use Doctrine\DBAL\Connection;

class PartnerSource
{

    private const CACHE_KEY = 'loyalty_partners_list_v3';

    /** @var Connection */
    private $connection;
    /** @var \Memcached */
    private $memcached;

    public function __construct(Connection $connection, \Memcached $memcached)
    {
        $this->connection = $connection;
        $this->memcached = $memcached;
    }

    /**
     * @return array ["PartnerLogin1" => 1 (thread count), ... ]
     */
    public function getPartners() : array
    {
        $result = $this->memcached->get(self::CACHE_KEY);
        if ($result === false) {
            $result = $this->connection->executeQuery("select Login, Threads from Partner 
            where LoyaltyAccess = 1 and State = 1 and Login <> 'awardwallet' and Threads > 0")->fetchAll(\PDO::FETCH_KEY_PAIR);
            $this->memcached->set(self::CACHE_KEY, $result, 60);
        };
        return $result;
    }

}