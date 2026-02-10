<?php

namespace AppBundle\Extension;

class MemcachedService extends \Memcached{

    public function __construct($memcached_host) {
        parent::__construct('appCacheB_' . $memcached_host);
        if(count($this->getServerList()) == 0){
            $this->addServer($memcached_host, 11211);
            $this->setOption(\Memcached::OPT_RECV_TIMEOUT, 500);
            $this->setOption(\Memcached::OPT_SEND_TIMEOUT, 500);
            $this->setOption(\Memcached::OPT_CONNECT_TIMEOUT, 500);
            $this->setOption(\Memcached::OPT_BINARY_PROTOCOL, true);
            // this option affects performance, login speed, required for AntiBruteforceLocker
            $this->setOption(\Memcached::OPT_TCP_NODELAY, true);
        }
    }

}