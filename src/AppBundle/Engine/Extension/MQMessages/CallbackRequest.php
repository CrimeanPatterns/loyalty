<?php

namespace AppBundle\Extension\MQMessages;

class CallbackRequest
{

    /** @var array */
    protected $ids;

    protected $method;

    protected $partner;

    protected $priority;

    protected $callbackRetries = 0;

    /**
     * @return array
     */
    public function getIds()
    {
        return $this->ids;
    }

    /**
     * @param array $ids
     * @return $this
     */
    public function setIds($ids)
    {
        $this->ids = $ids;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @param $method
     * @return $this
     */
    public function setMethod($method)
    {
        $this->method = $method;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPartner()
    {
        return $this->partner;
    }

    /**
     * @param mixed $partner
     * @return $this
     */
    public function setPartner($partner)
    {
        $this->partner = $partner;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param mixed $priority
     * @return $this
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * @return int
     */
    public function getCallbackRetries()
    {
        return $this->callbackRetries;
    }

    /**
     * @param $callbackRetries
     * @return $this
     */
    public function setCallbackRetries($callbackRetries)
    {
        $this->callbackRetries = $callbackRetries;
        return $this;
    }

    public function callbackDelay() {
        if($this->callbackRetries <= 0)
            return 0;

        return (2 ** $this->callbackRetries) * 60;
    }

}