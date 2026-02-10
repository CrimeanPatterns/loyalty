<?php

namespace AppBundle\Worker;

use AppBundle\Document\BaseDocument;
use AppBundle\Document\RaHotel;
use AppBundle\Document\RegisterAccount;
use AppBundle\Document\RewardAvailability;
use AppBundle\Extension\HttpCallbackSender;
use AppBundle\Extension\Loader;
use AppBundle\Extension\MongoCommunicator;
use AppBundle\Extension\MQMessages\CallbackRequest;
use AppBundle\Extension\MQSender;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use JMS\Serializer\SerializerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;

class SendCallbackWorker extends TaskWorker {

    const TIMEOUT = 30;
    const MAX_CALLBACK_RETRIES = 5;

    /** @var ProducerInterface */
    private $callbackProducer;

    /** @var SerializerInterface */
    private $serializer;

    /** @var MongoCommunicator */
    private $mongoCommunicator;

    /** @var MQSender */
    private $mqSender;

    /** @var HttpCallbackSender */
    private $httpCallbackSender;

    public function __construct(
        LoggerInterface $logger,
        ProducerInterface $producer,
        SerializerInterface $serializer,
        MongoCommunicator $mongoCommunicator,
        MQSender $mqSender,
        HttpCallbackSender $httpCallbackSender,
        Loader $loader,
        int $memoryLimit
    ) {
        parent::__construct($logger, $memoryLimit);
        $this->logger->info("worker ".$this->name." started");

        $this->callbackProducer = $producer;
        $this->serializer = $serializer;
        $this->mongoCommunicator = $mongoCommunicator;
        $this->mqSender = $mqSender;
        $this->httpCallbackSender = $httpCallbackSender;
    }

    public function execute(AMQPMessage $msg) {
        $this->checkLimits();
        $this->logger->pushProcessor([$this, "logProcessor"]);
        try{
            $callbackRequest = $this->unserialize($msg);
            if(!($callbackRequest instanceof CallbackRequest)){
                $this->logger->error("invalid callback message", ["body" => $msg->body]);
                return true;
            }
            $this->processRequest($callbackRequest);
        }
        catch(SkipException $e){
            $this->logger->notice("skip: ".$e->getMessage());
        }
        finally{
            $this->logger->popProcessor();
        }

        return true;
    }

    public function processRequest(CallbackRequest $callbackRequest)
    {
        if ($callbackRequest->getMethod() === RewardAvailability::METHOD_KEY) {
            $cls = RewardAvailability::class;
        } elseif ($callbackRequest->getMethod() === RegisterAccount::METHOD_KEY) {
            $cls = RegisterAccount::class;
        } elseif ($callbackRequest->getMethod() === RaHotel::METHOD_KEY) {
            $cls = RaHotel::class;
        } else {
            $cls = "AppBundle\\Document\\Check".ucfirst($callbackRequest->getMethod());
        }
        $rows = $this->mongoCommunicator->getRowsByIds($callbackRequest->getIds(), null, $cls);

        if (empty($rows)) {
            $this->logger->info("can't send callback, unavailable Ids", ["callback" => $callbackRequest]);
            return false;
        }
        $row = $rows[$callbackRequest->getIds()[0]];

        /** @var LoyaltyRequestInterface $request */
        if ($row instanceof RewardAvailability || $row instanceof RegisterAccount || $row instanceof RaHotel) {
            $request = $row->getRequest();
        } else {
            $request = $this->serializer->deserialize(json_encode($row->getRequest()), CheckAccountRequest::class, 'json');
        }

        $extra = [
            'method' => $callbackRequest->getMethod(),
            'partner' => $callbackRequest->getPartner(),
            'requestId' => $row->getId(),
        ];
        $this->logger->pushProcessor(
            function(array $record) use ($extra){
                $record['extra'] = array_merge($record['extra'], $extra);
                return $record;
            }
        );

        try {
            $lastRetry = $callbackRequest->getCallbackRetries() == self::MAX_CALLBACK_RETRIES;

            $response = [];
            foreach ($rows as $rowItem) {
                $response[] = $rowItem->getResponse();
            }

            $responseJson = $this->serializer->serialize($response, 'json');

            // for debug
            $bodySend = "{\"method\": \"{$row->getMethod()}\", \"response\": $responseJson}";
            $this->logger->info("sendCallback package size",
                ['bodySha1' => sha1($bodySend), 'package' => count($rows) > 1, 'bodySize' => strlen($bodySend)]);

            $callbackResult = $this->httpCallbackSender->sendCallback(
                $row->getPartner(),
                $request->getCallbackurl(),
                "{\"method\": \"{$row->getMethod()}\", \"response\": $responseJson}"
            );

            if (false !== $callbackResult) {
                $this->logCheckStat($callbackRequest, $rows, true);
            }

            if ($lastRetry && false == $callbackResult) {
                $this->logCheckStat($callbackRequest, $rows);
            }

            if (false === $callbackResult && $callbackRequest->getCallbackRetries() < self::MAX_CALLBACK_RETRIES) {
                $callbackRequest->setCallbackRetries($callbackRequest->getCallbackRetries() + 1);
                $delay = $callbackRequest->callbackDelay() * 1000;
                $this->logger->info(sprintf('retrying callback in %d ms', $delay));
                $this->callbackProducer->publish(serialize($callbackRequest), '',
                    ['application_headers' => ['x-delay' => ['I', $delay]]]);
            } elseif (false === $callbackResult) {
                $this->logger->info(sprintf('no retry: retries=%d', $callbackRequest->getCallbackRetries()));
            }
        }
        finally {
            $this->logger->popProcessor();
        }

        return true;
    }

    protected function logCheckStat(CallbackRequest $callback, $rows, $callbackSent = false){
        $method = "Check".ucfirst($callback->getMethod());
        $requestCls = "AppBundle\\Model\\Resources\\".$method."Request";

        /** @var BaseDocument $row */
        foreach ($rows as $row)
        {
            $request = $row->getRequest();
            if (is_array($request)) {
                $request = $this->serializer->deserialize(json_encode($row->getRequest()), $requestCls, 'json');
            }
            $this->mqSender->dumpPartnerStatistic($method, $request, $row, $callback->getCallbackRetries(), $callbackSent);
        }
    }

}