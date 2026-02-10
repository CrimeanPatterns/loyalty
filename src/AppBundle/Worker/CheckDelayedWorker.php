<?php
/**
 * Created by PhpStorm.
 * User: APuzakov
 * Date: 07.07.16
 * Time: 15:41
 */

namespace AppBundle\Worker;

use AppBundle\Extension\MQMessages\CheckPartnerMessage;
use AppBundle\Extension\MQSender;
use JMS\Serializer\Serializer;
use Monolog\Logger;
use PhpAmqpLib\Message\AMQPMessage;

class CheckDelayedWorker extends TaskWorker
{

    /** @var Serializer */
    protected $serializer;
    /** @var MQSender */
    private $mqSender;

    public function __construct(Logger $logger, MQSender $mqSender, Serializer $serializer, int $memoryLimit){
        parent::__construct($logger, $memoryLimit);
        $this->logger->info("worker ".$this->name." started");

        $this->serializer = $serializer;
        $this->mqSender = $mqSender;
    }

    public function execute(AMQPMessage $msg) {
        $this->checkLimits();
        $this->logger->pushProcessor([$this, "logProcessor"]);

        try {
            /** @var CheckPartnerMessage $msgDelayed */
            $msgDelayed = $this->unserialize($msg);
            $this->mqSender->sendCheckPartner($msgDelayed);
            $this->logger->info('pushed to check queue after delay', ['requestId' => $msgDelayed->getId(), 'partner' => $msgDelayed->getPartner()]);
        }
        catch(SkipException $e){
            $this->logger->notice("skip: ".$e->getMessage());
        }
        finally{
            $this->logger->popProcessor();
        }
    }

    /**
     * @param AMQPMessage $msg
     * @return CheckPartnerMessage
     * @throws SkipException
     */
    protected function unserialize(AMQPMessage $msg){
        $size = strlen($msg->body);
        if($size > self::MAX_MESSAGE_SIZE)
            throw new SkipException("message too big: $size bytes");

        $this->logger->debug("received message, $size bytes");
        try{
            $result = $this->serializer->deserialize($msg->body, CheckPartnerMessage::class, 'json');
        } catch(\Exception $e) {
            throw new SkipException($e->getMessage());
        }

        return $result;
    }

}