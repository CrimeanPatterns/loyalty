<?php

namespace AppBundle\Listener;

use AppBundle\Event\ParsingFinishedEvent;
use AwardWallet\Common\Parsing\LuminatiProxyManager\Client;

class LuminatiProxyManagerListener
{

    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function onParsingFinished(ParsingFinishedEvent $event)
    {
        $this->client->deleteAllPorts();
    }

}