<?php

namespace AppBundle\Extension;

class Locker
{

    /**
     * @var \Memcached
     */
    private $memcached;
    /**
     * @var string
     */
    private $myId;

    public function __construct(\Memcached $memcached, string $prefix = null, int $pid = null)
    {
        $this->memcached = $memcached;
        if ($prefix === null) {
            $prefix = gethostname();
        }
        if ($pid === null) {
            $pid = getmypid();
        }
        $this->myId = $prefix . "_" . $pid;
    }

    public function acquire(string $lockName, int $ttl) : bool
    {
        return $this->memcached->add($lockName, $this->myId, $ttl);
    }

    public function isLocked(string $lockName) : bool
    {
        $value = $this->memcached->get($lockName);
        return $value !== false && $value !== "deleted"; // sometimes memcached returns expired items
    }

    public function keep(string $lockName, int $ttl)
    {
        $info = $this->memcached->get($lockName, null, \Memcached::GET_EXTENDED);

        if (empty($info) || $info['value'] !== $this->myId) {
            return false;
        }

        if ($ttl > 60*60*24*30 && $ttl < (time() - 100)){
            $value = "deleted";
        } else {
            $value = $info['value'];
        }

        return $this->memcached->cas($info['cas'], $lockName, $value, $ttl);

    }

    public function release(string $lockName)
    {
        $this->keep($lockName, time() - 3600);
    }

}