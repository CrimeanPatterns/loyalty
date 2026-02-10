<?php

namespace AppBundle\Extension;

use AppBundle\Document\BaseDocument;
use AppBundle\Document\RaHotel;
use AppBundle\Document\RegisterAccount;
use AppBundle\Document\RewardAvailability;
use AppBundle\Extension\MQMessages\CheckPartnerMessage;
use AppBundle\Model\Resources\BaseCheckResponse;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\History;
use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use AppBundle\Model\Resources\RequestItemHistory;
use AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelRequest;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityRequest;
use JMS\Serializer\SerializerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Psr\Log\LoggerInterface;

class MQSender
{

    /** @var LoggerInterface */
    private $logger;
    /** @var AMQPChannel */
    private $mqChannel;
    /** @var array */
    private $isDeclaredQueues = [];
    /** @var SerializerInterface */
    private $serializer;
    /** @var ProducerInterface */
    private $delayedProducer;

    const QUEUE_MAX_PRIORITY = 10;
    const QUEUE_CHECK_NEWS = 'loyalty_check_news_%s';
    const QUEUE_CHECK_NEWS_TIMEOUT = 1*60*1000;

    private const PARTNER_QUEUE_NAME = 'loyalty_check_account_%s';
    private const ALL_PARTNERS_QUEUE_NAME = 'loyalty_check_account';
    const DUMP_STAT_QUEUE_NAME = 'loyalty_dump_statistic';
    /**
     * @var bool
     */
    private $partnerThreadLimits;

    public function __construct(
        LoggerInterface $logger,
        AMQPChannel $mqChannel,
        SerializerInterface $serializer,
        bool $partnerThreadLimits,
        ProducerInterface $delayedProducer)
    {
        $this->logger = $logger;
        $this->mqChannel = $mqChannel;
        $this->serializer = $serializer;
        $this->partnerThreadLimits = $partnerThreadLimits;
        $this->delayedProducer = $delayedProducer;
    }

    /**
     * @param string $queue
     * @param array|null $args
     */
    private function queueDeclare(AMQPChannel $channel, $queue, array $args = null)
    {
        if(in_array($queue, $this->isDeclaredQueues))
            return;

        $mqpTable = null;
        if(!empty($args))
            $mqpTable = new AMQPTable($args);

        $channel->queue_declare($queue, false, true, false, false, false, $mqpTable);
        $this->isDeclaredQueues[] = $queue;
    }

    public function declareCheckNewsQueue(AMQPChannel $channel, string $partner)
    {
        $this->queueDeclare($channel, sprintf(self::QUEUE_CHECK_NEWS, $partner), ["x-message-ttl" => self::QUEUE_CHECK_NEWS_TIMEOUT]);
    }

    public function getPartnerQueueName(string $partner) : string
    {
        return $this->partnerThreadLimits ? sprintf(self::PARTNER_QUEUE_NAME, $partner) : self::ALL_PARTNERS_QUEUE_NAME;
    }

    /**
     * @param string $partner
     */
    public function declarePartnerQueue(AMQPChannel $channel, string $partner)
    {
        $this->queueDeclare($channel, $this->getPartnerQueueName($partner), ["x-max-priority" => self::QUEUE_MAX_PRIORITY]);
    }

    /**
     * Sending message to the queue
     * @param AMQPMessage $msg
     * @param $queue
     */
    private function send(AMQPMessage $msg, $queue)
    {
        $this->logger->info("MQSender.send " . $queue . " " . $msg->body);
        $this->mqChannel->basic_publish($msg, '', $queue);
    }

    public function sendCheckNews($partner)
    {
        $this->declareCheckNewsQueue($this->mqChannel, $partner);
        $message = new AMQPMessage('new', ['content_type' => 'text/plain', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
        $this->send($message, sprintf(MQSender::QUEUE_CHECK_NEWS, $partner));
    }

    /**
     * Sending message to the partner queue
     * @param CheckPartnerMessage $msg
     */
    public function sendCheckPartner(CheckPartnerMessage $msg, ?int $messageTtl = null)
    {
        $properties = ['priority' => $msg->getPriority(), 'content_type' => 'text/plain', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT];
        if ($messageTtl) {
            $properties['x-message-ttl'] = $messageTtl;
        }

        $message = new AMQPMessage(
            serialize([
                'id' => $msg->getId(),
                'method' => $msg->getMethod()
            ]),
            $properties
        );

        $this->declarePartnerQueue($this->mqChannel, $msg->getPartner());
        $this->send($message, $this->getPartnerQueueName($msg->getPartner()));
    }

    public function sendCheckPartnerDelay(CheckPartnerMessage $msg, int $delay, ?int $messageTtl = null)
    {
        $this->declarePartnerQueue($this->mqChannel, $msg->getPartner());

        $headers = ['x-delay' => ['I', $delay * 1000]];
        if ($messageTtl) {
            $headers['x-message-ttl'] = ['I', $messageTtl];
        }

        $this->delayedProducer->publish(
            $this->serializer->serialize($msg, 'json'),
            '',
            ['application_headers' => $headers]
        );
    }

    public function dumpPartnerStatistic($method, LoyaltyRequestInterface $request, BaseDocument $row, $callbackRetries, $callbackSent): void
    {
        if ($row instanceof RewardAvailability || $row instanceof RaHotel || $row instanceof RegisterAccount) {
            $response = $row->getResponse();
        } else {
            $responseCls = "AppBundle\\Model\\Resources\\" . ( $row->getApiVersion() > 1 ? "V" . $row->getApiVersion() . "\\" : "" ) . $method . "Response";
            /** @var BaseCheckResponse $response */
            $response = $this->serializer->deserialize(json_encode($row->getResponse()), $responseCls, 'json');
        }

        $checkComplete = new \DateTime();
        $statistic = [
            "message" => "Partner statistic",
            "ApiVersion" => $row->getApiVersion(),
            "Method" => $method,
            "RequestID" => $row->getId(),
            "Partner" => $row->getPartner(),
            "UserID" => $request->getUserId(),
            "UserDay" => $request->getUserId() . '_' . $response->getRequestdate()->format('Y-m-d'),
            "UserData" => $request->getUserData(),
            "Provider" => $request->getProvider(),
            "Priority" => $request->getPriority(),
            /* сколько раз пробовали парсить повторно, в связи с ошибками в парсере, или уничтожения ватчдогом */
            "ParseRetries" => $row->getRetries(),
            "CallbackRetries" => $callbackRetries,
            "CallbackSent" => $callbackSent,
            "RequestDateTime" => $response->getRequestdate()->format('Y-m-d H:i:s'),
            /* когда начали первый раз проверять этот аккаунт, не важно если потом будет троттлинг. Последующие проверки (в случае ошибки в парсере, остановки ватчдогом) не приводят к перезаписи этого поля, если оно уже заполнено */
            "CheckStartDateTime" => $row->getFirstCheckDate()->format('Y-m-d H:i:s'),
            "CheckCompleteDateTime" => $checkComplete->format('Y-m-d H:i:s'),
            "FullProcessingTime" => $checkComplete->getTimestamp() - $response->getRequestdate()->getTimestamp(),
            /* секунды, CheckStartDate - RequestDate  */
            "QueueTime" => $row->getFirstCheckDate()->getTimestamp() - $response->getRequestdate()->getTimestamp(),
            /* секунды, сколько аккаунт суммарно провел времени в очередях троттлинга */
            "ThrottledTime" => is_null($row->getThrottledTime()) ? 0 : $row->getThrottledTime(),
            "ParsingTime" => $row->getParsingTime(),
            "CaptchaTime" => $row->getCaptchaTime(),
            "ErrorCode" => $response->getState(),
        ];
        if ($request instanceof RewardAvailabilityRequest || $request instanceof RaHotelRequest) {
            $statistic["ParsingTimeGT90"] = $statistic["ParsingTime"] > 90 ? 1 : 0;
            $statistic["FullProcessingTimeGT90"] = $statistic["FullProcessingTime"] > 90 ? 1 : 0;
            $statistic["ParsingTimeGT120"] = $statistic["ParsingTime"] > 120 ? 1 : 0;
            $statistic["FullProcessingTimeGT120"] = $statistic["FullProcessingTime"] > 120 ? 1 : 0;
            $statistic["RequestData"] = $this->serializer->serialize($request, 'json');
            $statistic["ResponseData"] = $this->serializer->serialize($response, 'json');
        }
        if ($request instanceof CheckAccountRequest) {
            /** @var CheckAccountRequest $request */
            $statistic['RequestParseItineraries'] = $request->getParseitineraries() ? 'true' : 'false';
            $statistic['LoginHash'] = md5($request->getLogin());
            $statistic['LoginProviderHash'] = md5($request->getLogin() . $request->getProvider());

            if ($request->getHistory() instanceof RequestItemHistory) {
                $statistic['RequestParseHistory'] = in_array(
                    $request->getHistory()->getRange(),
                    [History::HISTORY_COMPLETE, History::HISTORY_INCREMENTAL]
                ) ? 'true' : 'false';
            }
        }

        $this->logger->info("statistic", $statistic);

        $this->queueDeclare($this->mqChannel, self::DUMP_STAT_QUEUE_NAME);
        $message = new AMQPMessage(serialize($statistic), ['content_type' => 'text/plain', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
        $this->send($message, self::DUMP_STAT_QUEUE_NAME);
    }

    /**
     * Declaring temporary exclusive queue
     * @returns string - queue name
     */
    public function declareTmpQueue() : ?string
    {
        $response =  $this->mqChannel->queue_declare('', false, false, true);

        return $response[0] ?? null;
    }

    /**
     * Pushing data to queue
     * @param mixed $data
     * @param string $queue
     */
    public function pushDataToQueue($data, $queue)
    {
        $message = new AMQPMessage(serialize($data), ['content_type' => 'text/plain', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT]);
        $this->send($message, $queue);
    }

    public function waitQueueMessage($queue, $callback, $timeout = 30, $consumerTag = '', $exclusive = true, $no_ack = false)
    {
        $this->mqChannel->basic_qos(0, 1, null);
        $this->mqChannel->basic_consume($queue, $consumerTag, false, $no_ack, $exclusive, false, $callback);
        $this->mqChannel->wait(null, false, $timeout);
    }
}