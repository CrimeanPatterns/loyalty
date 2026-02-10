<?php
namespace AppBundle\Extension;

use AppBundle\Event\ExtendTimeLimitEvent;
use AppBundle\Event\WatchdogKillProcessEvent;
use AppBundle\Extension\MQMessages\WatchdogMessage;
use AppBundle\Worker\CheckExecutor\BaseExecutor;
use JMS\Serializer\Serializer;
use Monolog\Logger;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Watchdog
{

    const QUEUE_NAME = 'watchdog_%s';
    const MSG_START = 'start';
    const MSG_STOP = 'stop';
    const MSG_INCREASE = 'increase';
    const MSG_CONTEXT = 'context';
    const DEFAULT_PROCESSING_TIMEOUT = 60;
    const HARD_PROCESSING_TIMEOUT = 20 * 60;
    const AUDITOR_TIMEOUT = 1;
    /** @var LoggerInterface */
    private $logger;
    /** @var AMQPChannel */
    private $mqChannel;
    /** @var Serializer */
    private $serializer;
    /** @var EventDispatcherInterface */
    private $eventDispatcher;
    /** @var TimeCommunicator */
    private $time;
    /**
     * @var WatchdogMessage[]
     */
    private $activeProcesses = [];
    /**
     * @var ThreadFactory
     */
    private $threadFactory;
    /**
     * @var ThreadStats
     */
    private $threadStats;

    public function __construct(LoggerInterface $logger, AMQPChannel $mqChannel, Serializer $serializer, EventDispatcherInterface $eventDispatcher, TimeCommunicator $time, ThreadFactory $threadFactory, ThreadStats $threadStats)
    {
        $this->logger = $logger;
        $this->mqChannel = $mqChannel;
        $this->serializer = $serializer;
        $this->eventDispatcher = $eventDispatcher;
        $this->time = $time;

        $this->queue = sprintf(self::QUEUE_NAME, gethostname());
        $this->mqChannel->queue_declare($this->queue, false, true, false, true, false, new AMQPTable(['x-expires' => 60*60*1000, "x-message-ttl" => 30*60*1000]));
        $this->threadFactory = $threadFactory;
        $this->threadStats = $threadStats;
    }

    /**
     * Run Watchdog method
     * @throws WatchdogException
     */
    public function run($loop = true){
        do {
            do {
                $checkMessageResult = $this->checkMessage();
            } while($checkMessageResult);

            if (!$this->killSomeone()) {
                $this->time->sleep(self::AUDITOR_TIMEOUT);
            }
        } while($loop);
    }

    private function checkMessage()
    {
        /** @var AMQPMessage $message */
        $message = $this->mqChannel->basic_get($this->queue);
        if(!$message)
            return false;

        /** @var WatchdogMessage $msg */
        $msg = $this->serializer->deserialize($message->getBody(), WatchdogMessage::class, 'json');
        if(!in_array($msg->getType(), [self::MSG_START, self::MSG_STOP, self::MSG_INCREASE, self::MSG_CONTEXT])){
            $this->mqChannel->basic_ack($message->delivery_info['delivery_tag']);
            throw new WatchdogException('Unknown message type');
        }

        if($msg->getType() === self::MSG_START) {
            $this->logger->debug("starting monitoring of " . $msg->getPid() . ", end time: " . date("Y-m-d H:i:s", $msg->getStopTime()));
            if (isset($this->activeProcesses[$msg->getPid()])) {
                $this->logger->warning("watchdog monitoring overlap: " . $msg->getPid(), $this->activeProcesses[$msg->getPid()]->getContext()['logContext']);
            }
            $this->activeProcesses[$msg->getPid()] = $msg;
        }

        if($msg->getType() === self::MSG_STOP && isset($this->activeProcesses[$msg->getPid()])) {
            if (isset($this->activeProcesses[$msg->getPid()])) {
                $existMsg = $this->activeProcesses[$msg->getPid()];
                unset($this->activeProcesses[$msg->getPid()]);
                $this->logger->debug("stopped monitoring of " . $msg->getPid(), $existMsg->getContext()['logContext']);
            }
        }

        if($msg->getType() === self::MSG_INCREASE && isset($this->activeProcesses[$msg->getPid()])){
            /** @var WatchdogMessage $existMsg */
            $existMsg = $this->activeProcesses[$msg->getPid()];
            $newStopTime = $this->time->getCurrentTime() + $msg->getIncreaseTime();
            if($newStopTime > $existMsg->getStopTime())
                $existMsg->setStopTime($newStopTime);

            if($existMsg->getStartTime() + self::HARD_PROCESSING_TIMEOUT < $existMsg->getStopTime())
                $existMsg->setStopTime($existMsg->getStartTime() + self::HARD_PROCESSING_TIMEOUT);
            $this->logger->info("time of " . $msg->getPid() . " extended to " . date("Y-m-d H:i:s", $existMsg->getStopTime()), $existMsg->getContext()['logContext']);
        }

        if($msg->getType() === self::MSG_CONTEXT && isset($this->activeProcesses[$msg->getPid()])){
            /** @var WatchdogMessage $existMsg */
            $existMsg = $this->activeProcesses[$msg->getPid()];
            $oldContext = $existMsg->getContext();
            $newContext = $msg->getContext();
            if (
                isset($oldContext['logContext'])
                && isset($oldContext['logContext']['userData'])
                && isset($newContext['logContext'])
                && isset($newContext['logContext']['userData'])
                && $newContext['logContext']['userData'] != $oldContext['logContext']['userData']
            ) {
                $this->logger->warning("switched context on thread {$msg->getPid()} from {$oldContext['logContext']['userData']} to {$newContext['logContext']['userData']}");
            }
            $existMsg->addContext($newContext);
            $this->logger->debug("added context to " . $msg->getPid(), $existMsg->getContext()['logContext']);
        }

        // сообщение успешно обработано
        $this->mqChannel->basic_ack($message->delivery_info['delivery_tag']);
        return true;
    }

    private function killSomeone() : bool
    {
        /** @var WatchdogMessage $msg */
        foreach($this->activeProcesses as $pid => $msg)
        {
            if($this->time->getCurrentTime() >= $msg->getStopTime()) {
                $this->killProcess($msg);
                // killing takes 3 seconds, we do not want to hang in this loop for long amount of time
                // processes may start / stop in this time
                // we should read commands from new processes first
                return true;
            }
        }

        return false;
    }

    private function killProcess(WatchdogMessage $msg)
    {
        $this->logger->notice('Killing process by watchdog', array_merge($msg->getContext()['logContext'], ['WorkerPID' => $msg->getPid()]));

        $killResult = posix_kill($msg->getPid(), SIGTERM);
        if(!$killResult)
            posix_kill($msg->getPid(), SIGKILL);

        $this->threadFactory->removeByPid($msg->getPartner(), $msg->getPid());
        unset($this->activeProcesses[$msg->getPid()]);

        $this->eventDispatcher->dispatch(WatchdogKillProcessEvent::NAME, new WatchdogKillProcessEvent($msg->getStartTime(), $msg->getContext()));
        $this->logger->notice('Process killed by watchdog', array_merge($msg->getContext()['logContext'], ['WorkerPID' => $msg->getPid()]));
    }

    /**
     * Start watching to proccess
     * @param int $pid
     * @param string $partner
     * @param int $processTimeout
     */
    public function start($pid, $partner, $processTimeout = self::DEFAULT_PROCESSING_TIMEOUT)
    {
        $this->logger->debug("requested watchdog monitoring of $pid");
        $msg = new WatchdogMessage();
        $msg->setPartner($partner)
            ->setPid($pid)
            ->setStartTime($this->time->getCurrentTime())
            ->setStopTime($this->time->getCurrentTime() + $processTimeout)
            ->setType(self::MSG_START)
            ->setContext(['logContext' => [
                'pid' => $pid,
            ]])
        ;

        $this->sendMessage($msg);
    }

    /**
     * Sending messages to watchdog queue
     * @param WatchdogMessage $msg
     */
    private function sendMessage(WatchdogMessage $msg){
        $message = new AMQPMessage(
            $this->serializer->serialize($msg, 'json'),
            array('content_type' => 'text/plain', 'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT)
        );
        $this->mqChannel->basic_publish($message, '', $this->queue);
    }

    /**
     * Stop watching to proccess
     * @param int $pid
     */
    public function stop($pid){
        $this->logger->debug("cancelling watchdog monitoring of $pid");
        $msg = new WatchdogMessage();
        $msg->setPid($pid)
            ->setType(self::MSG_STOP);

        $this->sendMessage($msg);
    }

    /**
     * Increase stop time for process
     * @param int $pid
     * @param int $increaseTime
     */
    public function increase(int $pid, int $increaseTime)
    {
        $msg = new WatchdogMessage();
        $msg->setPid($pid)
            ->setType(self::MSG_INCREASE)
            ->setIncreaseTime($increaseTime);

        $this->sendMessage($msg);
    }

    public function addContext(int $pid, array $context)
    {
        $msg = new WatchdogMessage();
        $msg->setContext($context)
            ->setPid($pid)
            ->setType(self::MSG_CONTEXT);

        $this->sendMessage($msg);
    }

    public function onExtendTimeLimit(ExtendTimeLimitEvent $event)
    {
        $this->logger->info("extending thread watchdog timer on {$event->getTime()} seconds");
        $this->increase(getmypid(), $event->getTime());
    }

}