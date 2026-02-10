<?php

namespace AppBundle\Service;

class HotBrowserFinder
{

    /**
     * @var \Memcached
     */
    private $memcached;

    public function __construct(\Memcached $memcached) {

        $this->memcached = $memcached;
    }

    public function getSeleniumDriverState(int $maxSessions) : ?array
    {

    }

}