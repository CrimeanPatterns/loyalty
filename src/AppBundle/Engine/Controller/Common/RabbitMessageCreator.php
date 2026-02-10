<?php


namespace AppBundle\Controller\Common;


use AppBundle\Extension\MQMessages\CheckPartnerMessage;
use AppBundle\Extension\MQSender;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class RabbitMessageCreator
{

    /** @var TokenStorageInterface */
    private $tokenStorage;

    /** @var MQSender */
    private $mqSender;

    public function __construct(TokenStorageInterface $tokenStorage, MQSender $mqSender)
    {
        $this->tokenStorage = $tokenStorage;
        $this->mqSender = $mqSender;
    }

    public function createRabbitMessage(string $mongoRowId, int $priority, string $methodKey, int $delay = 0, ?int $messageTtl = null): void
    {
        $partner = $this->tokenStorage->getToken()->getUser()->getUsername();

        $mqPartnerMsg = new CheckPartnerMessage($mongoRowId, $methodKey, $partner, $priority);

        if($delay > 0) {
            $this->mqSender->sendCheckPartnerDelay($mqPartnerMsg, $delay, $messageTtl);
        } else {
            $this->mqSender->sendCheckPartner($mqPartnerMsg, $messageTtl);
        }

        $this->mqSender->sendCheckNews($partner);
    }
}