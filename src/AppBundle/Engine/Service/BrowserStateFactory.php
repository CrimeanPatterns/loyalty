<?php

namespace AppBundle\Service;

use AppBundle\Document\BrowserState;
use Doctrine\ODM\MongoDB\DocumentManager;

class BrowserStateFactory
{
    /** @var string */
    protected $aesKey;
    /** @var DocumentManager */
    protected $manager;

    public function __construct($aes, DocumentManager $manager)
    {
        $this->aesKey = $aes;
        $this->manager = $manager;
    }

    public function save(string $key, string $state): void
    {
        $oldState = $this->load($key);
        if (!$oldState) {
            $this->createRow($key, $state);
            return;
        }
        $this->updateRow($key, $state);
    }

    public function load(string $key): ?string
    {
        /** @var BrowserState $row */
        $row = $this->manager->getRepository(BrowserState::class)->findOneBy(['key' => $key]);
        if (!$row) {
            return null;
        }
        return $this->decodeBrowserState($row->getState());
    }

    public function clear(string $key): bool
    {
        /** @var BrowserState $row */
        $row = $this->manager->getRepository(BrowserState::class)->findOneBy(['key' => $key]);
        if (!$row) {
            return false;
        }
        $this->manager
            ->createQueryBuilder(BrowserState::class)
            ->remove()
            ->field('key')->equals($key)
            ->getQuery()
            ->execute();
        return true;
    }

    private function createRow($key, $state): BrowserState
    {
        /** @var BrowserState $row */
        $row = new BrowserState();
        $row->setKey($key)
            ->setState($this->encodeBrowserState($state))
            ->setCreatedate(new \DateTime())
            ->setUpdatedate(new \DateTime());

        $this->manager->persist($row);
        $this->manager->flush(); // getting id before flush is unreliable

        return $row;
    }

    private function updateRow($key, $state): BrowserState
    {
        /** @var BrowserState $row */
        $row = $this->manager->getRepository(BrowserState::class)->findOneBy(['key' => $key]);
        $row->setState($this->encodeBrowserState($state))
            ->setUpdatedate(new \DateTime());

        $this->manager->persist($row);
        $this->manager->flush();
        return $row;
    }

    /**
     * @param string $state
     * @return bool|string
     */
    protected function decodeBrowserState(string $state)
    {
        return AESDecode(base64_decode($state), $this->aesKey);
    }

    /**
     * @param $state
     * @return string
     */
    protected function encodeBrowserState($state)
    {
        return base64_encode(AESEncode($state, $this->aesKey));
    }

}