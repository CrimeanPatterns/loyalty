<?php

namespace AppBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document
 * @MongoDB\Indexes({
 *   @MongoDB\Index(keys={"creationDate"="asc"}),
 * })
 */
class Thread
{

    /**
     * @MongoDB\Id
     */
    private $id;
    /**
     * @var string
     * @MongoDB\Field
     */
    private $hostName;
    /**
     * @var int
     * @MongoDB\Integer
     */
    private $pid;
    /**
     * @var string
     * @MongoDB\Field
     */
    private $partner;
    /**
     * @var bool
     * @MongoDB\Field
     */
    private $free;
    /**
     * @var \DateTimeImmutable
     * @MongoDB\Date
     */
    private $creationDate;
    /**
     * @var \DateTimeImmutable
     * @MongoDB\Date
     */
    private $updateDate;

    public function __construct(string $hostName, int $pid, bool $free)
    {
        $this->hostName = $hostName;
        $this->pid = $pid;
        $this->free = $free;
        $this->creationDate = new \DateTimeImmutable();
        $this->stillActive();
    }

    public function stillActive() : void
    {
        $this->updateDate = new \DateTimeImmutable();
    }

    /**
     * @return bool
     */
    public function isExpired(): bool
    {
        $timeout = $this->free ? 60 : 600;

        $baseDate = $this->updateDate;
        // for migration time, updateDate was added later
        if ($baseDate === null) {
            $baseDate = $this->creationDate;
        }

        return time() > ($baseDate->getTimestamp() + $timeout);
    }

    /**
     * @return string
     */
    public function getPartner(): ?string
    {
        return $this->partner;
    }

    public function setFree(bool $free) : self
    {
        $this->free = $free;

        return $this;
    }

    public function setPartner(string $partner): void
    {
        $this->partner = $partner;
    }

    public function isFree(): bool
    {
        return $this->free;
    }

    /**
     * @return string
     */
    public function getHostName(): string
    {
        return $this->hostName;
    }

    /**
     * @return int
     */
    public function getPid(): int
    {
        return $this->pid;
    }

}