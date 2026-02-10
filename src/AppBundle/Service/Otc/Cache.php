<?php


namespace AppBundle\Service\Otc;


use AppBundle\Extension\MemcachedService;
use Memcached;
use Psr\Log\LoggerInterface;

class Cache
{

    /** @var MemcachedService  */
    private $memcached;
    /** @var LoggerInterface  */
    private $logger;

    const TTL = 15 * 60;
    private const OTC_SUFFIX = 'otc';
    private const LOCK_SUFFIX = 'lck';

    public function __construct(LoggerInterface $logger, Memcached $memcached)
    {
        $this->memcached = $memcached;
        $this->logger = $logger;
    }

    public function saveOtc(string $accountKey, string $otc)
    {
        if ($this->isLocked($accountKey)) {
            $this->logger->info('account is otc locked, skipping', ['accountKey' => $accountKey]);
            return;
        }
        if ($this->memcached->get($this->key($accountKey, self::OTC_SUFFIX))) {
            $this->logger->info('otc is already saved for this account, locking');
            $this->deleteOtc($accountKey);
            $this->lock($accountKey);
        }
        else {
            $this->memcached->set($this->key($accountKey, self::OTC_SUFFIX), $otc, self::TTL);
            $this->logger->info('saved otc', ['otc' => $otc]);
        }
    }

    public function getOtc(string $accountKey): ?string
    {
        return ($otc = $this->memcached->get($this->key($accountKey, self::OTC_SUFFIX))) ? $otc : null;
    }

    public function deleteOtc(string $accountKey)
    {
        $this->memcached->delete($this->key($accountKey, self::OTC_SUFFIX));
    }

    public function lock(string $accountKey)
    {
        $this->memcached->set($this->key($accountKey, self::LOCK_SUFFIX), '1', self::TTL);
    }

    public function release(string $accountKey)
    {
        $this->memcached->delete($this->key($accountKey, self::LOCK_SUFFIX));
    }

    public function isLocked(string $accountKey): bool
    {
        return !!$this->memcached->get($this->key($accountKey, self::LOCK_SUFFIX));
    }

    private function key(string $accountKey, string $suffix): string
    {
        return sprintf('llt_acc_%s_%s', $suffix, $accountKey);
    }

}