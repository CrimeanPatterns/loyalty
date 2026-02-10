<?php

namespace AppBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * @MongoDB\Document
 * @MongoDB\Indexes({
 *   @MongoDB\Index(keys={"updatedate"="desc"}),
 * })
 */

class BrowserState
{
    /** @MongoDB\Id */
    protected $id;

    /** @MongoDB\Field */
    protected $key;

    /** @MongoDB\Field */
    protected $state;

    /** @MongoDB\Date */
    protected $createDate;

    /** @MongoDB\Date */
    protected $updatedate;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     * @return BrowserState
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param mixed $key
     * @return BrowserState
     */
    public function setKey($key)
    {
        $this->key = $key;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param mixed $state
     * @return BrowserState
     */
    public function setState($state)
    {
        $this->state = $state;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCreateDate()
    {
        return $this->createDate;
    }

    /**
     * @param mixed $createDate
     * @return BrowserState
     */
    public function setCreateDate($createDate)
    {
        $this->createDate = $createDate;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getUpdatedate()
    {
        return $this->updatedate;
    }

    /**
     * @param mixed $updatedate
     * @return BrowserState
     */
    public function setUpdatedate($updatedate)
    {
        $this->updatedate = $updatedate;
        return $this;
    }
}