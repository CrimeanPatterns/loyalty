<?php
namespace AppBundle\Command;

use AppBundle\Event\ExtendTimeLimitEvent;
use AppBundle\Extension\MQSender;
use AppBundle\Extension\PartnerSource;
use AppBundle\Extension\Thread;
use AppBundle\Document\Thread as StatThread;
use AppBundle\Extension\ThreadFactory;
use AppBundle\Extension\ThreadStats;
use AppBundle\Extension\Watchdog;
use AppBundle\Service\ExceptionLogger;
use AppBundle\Worker\CheckWorker;
use AppBundle\Worker\ExitException;
use AwardWallet\Common\AWS\Util;
use AwardWallet\Common\Monolog\Processor\TraceProcessor;
use Monolog\Logger;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckWorkerCommand extends Command
{

    const QUEUE_TIMEOUT = 5;

    /** @var Logger */
    private $logger;
    /** @var AMQPChannel */
    private $mqPartnerChannel;
    /** @var AMQPChannel */
    private $mqNewsChannel;
    /** @var MQSender */
    private $mqSender;
    /** @var CheckWorker */
    private $worker;
    /** @var ThreadFactory */
    private $threadFactory;
    /** @var Watchdog */
    private $watchdog;
    /**
     * @var ThreadStats
     */
    private $threadStats;
    /**
     * @var Thread
     */
    private $thread;
    private $messageCount = 0;
    private $startMemory;
    /**
     * @var Util
     */
    private $awsUtil;
    /**
     * @var string
     */
    private $localIp;
    /**
     * @var StatThread
     */
    private $statThread;
    /**
     * @var bool
     */
    private $partnerThreadLimits;

    public function __construct(
        LoggerInterface $logger,
        ThreadFactory $threadFactory,
        CheckWorker $worker,
        AMQPStreamConnection $mqConnection,
        MQSender $mqSender,
        Watchdog $watchdog,
        ThreadStats $threadStats,
        Util $awsUtil,
        bool $partnerThreadLimits
    )
    {
        $this->logger = $logger;
        $this->threadFactory = $threadFactory;
        $this->worker = $worker;
        $this->mqSender = $mqSender;
        $this->watchdog = $watchdog;
        $this->threadStats = $threadStats;

        $this->mqPartnerChannel = $mqConnection->channel();
        $this->mqPartnerChannel->basic_qos(0, 1, null);
        $this->mqNewsChannel = $mqConnection->channel();
        $this->mqNewsChannel->basic_qos(0, 1, null);

        parent::__construct();
        $this->awsUtil = $awsUtil;
        $this->partnerThreadLimits = $partnerThreadLimits;
    }

    public function onExtendTimeLimit(ExtendTimeLimitEvent $event)
    {
        if ($this->thread !== null) {
            $this->logger->info("extending thread lifetime");
            $this->thread->keep();
        }
    }

    protected function configure() {
        $this
            ->setDescription('Check Account and Retrive by ConfNo main Worker')
            ->addOption('partner', 'p', InputOption::VALUE_REQUIRED, 'only this partner')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var Thread $thread */
        $this->installSignalHandlers();
        $localIp = $this->awsUtil->getLocalIP();
        $this->statThread = $this->threadStats->register($localIp, getmypid());

        if (!$this->partnerThreadLimits) {
            $this->logger->info("no partner thread limits");
        }

        $exceptionLogger = new ExceptionLogger(
            $this->logger,
            [ExitException::class => Logger::INFO],
        );

        try {
            $this->addLogProcessor();
            $partner = $input->getOption('partner');

            if ($partner !== null || !$this->partnerThreadLimits) {
                $this->runWithoutLocks($partner ?? 'all', $exceptionLogger);
            } else {
                $this->runWithLocks($partner, $exceptionLogger);
            }
        } catch (\Throwable $executeThrowable) {
            $exceptionLogger->tryLog($executeThrowable, __METHOD__ . ': try-catch block: ');

            throw $executeThrowable;
        } finally {
            $this->logger->info(__METHOD__ . ': entering finally block');

            try {
                $this->threadStats->remove($this->statThread);
            } catch (\Throwable $executeFinallyThrowable) {
                $exceptionLogger->tryLog($executeFinallyThrowable, __METHOD__ . ': finally block: ');

                throw $executeFinallyThrowable;
            }
        }

        $this->logger->info("worker finished");
    }

    protected function processMessages(int $waitTimeout, string $partner, ExceptionLogger $exceptionLogger) : bool
    {
        $this->statThread->setPartner($partner);
        $this->statThread->setFree(true);
        $this->logger->debug("free thread");
        $this->threadStats->update($this->statThread);

        $queueName = $this->mqSender->getPartnerQueueName($partner);
        $message = $this->waitForMessage($this->mqPartnerChannel, $queueName, $waitTimeout);

        if ($message) {
            $acked = false;
            $this->watchdog->start(getmypid(), $partner, ThreadFactory::TTL);
            try {
                $this->statThread->setFree(false);
                $this->threadStats->update($this->statThread);
                $this->logger->debug("busy");
                try {
                    $this->worker->execute($message);
                    $this->mqPartnerChannel->basic_ack($message->delivery_info['delivery_tag']);
                    $acked = true;
                    $this->messageCount++;
                    $memoryUsage = round(memory_get_usage() / 1024 / 1024);
                    $memoryLeak = $memoryUsage - $this->startMemory;
                    $this->logger->info("message processed", ["memoryUsage" => $memoryUsage, "messageCount" => $this->messageCount, "memoryLeak" => $memoryLeak, "realMemory" => round(memory_get_usage(true) / 1024 / 1024)]);
                    if ($this->messageCount > 20 && $memoryLeak > 30) {
                        throw new ExitException("exiting to recycle memory, leak: " . $memoryLeak);
                    }
                } catch (\Throwable $innerProcessMessageThrowable) {
                    $exceptionLogger->tryLog($innerProcessMessageThrowable, __METHOD__ . ': inner try-catch block: ');

                    throw $innerProcessMessageThrowable;
                } finally {
                    $this->logger->info(__METHOD__ . ': entering inner finally block');

                    try {
                        $this->statThread->setFree(false);
                        $this->threadStats->update($this->statThread);
                    } catch (\Throwable $innerProcessMessageFinallyThrowable) {
                        $exceptionLogger->tryLog($innerProcessMessageFinallyThrowable, __METHOD__ . ': inner finally block: ');

                        throw $innerProcessMessageFinallyThrowable;
                    }
                }
            } finally {
                $this->logger->info(__METHOD__ . ': entering outer finally block');

                try {
                    $this->watchdog->stop(getmypid());
                    if (!$acked) {
                        $this->logger->info("nacking message");
                        $this->mqPartnerChannel->basic_nack($message->delivery_info['delivery_tag'], false, true);
                    }
                } catch (\Throwable $outerProcessMessageFinallyThrowable) {
                    $exceptionLogger->tryLog($outerProcessMessageFinallyThrowable, __METHOD__ . ': outer finally block: ');

                    throw $outerProcessMessageFinallyThrowable;
                }
            }

            return true;
        }

        return false;
    }

    private function waitNews(string $partner)
    {
        $this->mqSender->declareCheckNewsQueue($this->mqNewsChannel, $partner);
        $this->mqNewsChannel->basic_consume(
            sprintf(MQSender::QUEUE_CHECK_NEWS, $partner),
            "",
            false,
            false,
            false,
            false,
            function(AMQPMessage $message){
                $this->logger->info("got news");
                $this->mqNewsChannel->basic_ack($message->delivery_info['delivery_tag']);
            }
        );

        try {
            $this->mqNewsChannel->wait(null, false, self::QUEUE_TIMEOUT);
        } catch(AMQPTimeoutException $e) {}
    }

    private function waitForMessage(AMQPChannel $channel, string $queueName, int $waitTimeout) : ?AMQPMessage
    {
        $result = null;

        if ($waitTimeout > 0) {
            $consumerTag = 'check_worker_' . gethostname() . '_' . getmypid();
            $channel->basic_consume($queueName, $consumerTag, false, false, false, true, function(AMQPMessage $message) use (&$result){
                $result = $message;
            });
            try {
                $channel->wait(null, $waitTimeout === 0, $waitTimeout);
                $this->logger->debug("got new message from $queueName");
            } catch (AMQPTimeoutException $e) { }
            $channel->basic_cancel($consumerTag);
        } else {
            $result = $this->mqPartnerChannel->basic_get($queueName);
        }

        return $result;
    }

    private function installSignalHandlers()
    {
        pcntl_async_signals(true);

        $sigHandler = function($signal) {
            $this->logger->notice("got signal $signal, stopping worker");

            if ($this->thread !== null) {
                $this->thread->stop();
            }

            if ($this->statThread !== null) {
                $this->threadStats->remove($this->statThread);
            }

            exit(0);
        };

        pcntl_signal(SIGTERM, $sigHandler);
        pcntl_signal(SIGINT, $sigHandler);
    }

    private function addLogProcessor()
    {
        $this->logger->pushProcessor(function(array $record){
            $record['extra']['worker'] = 'CheckWorker';
            if (isset($this->thread)) {
                $record['extra']['worker_owner'] = $this->thread->getPartner();
            }
            return $record;
        });
    }

    private function runWithoutLocks(string $partner, ExceptionLogger $exceptionLogger)
    {
        $this->logger->info("worker started, running without locks for $partner");
        $this->mqSender->declarePartnerQueue($this->mqPartnerChannel, $partner);
        try {
            while (true) {
                // timeout 30 to update threads
                $this->processMessages(30, $partner, $exceptionLogger);
            }
        } catch (ExitException $e) {
            $this->logger->info("exiting, reason: " . $e->getMessage());
        }
    }

    private function runWithLocks(?string $partner, ExceptionLogger $exceptionLogger)
    {
        $this->thread = $this->threadFactory->create($partner);
        $this->startMemory = round(memory_get_usage() / 1024 / 1024);
        $this->logger->info("worker started, partner: {$partner}, dedicated: " . json_encode($this->thread->isDedicated()), ["memoryUsage" => $this->startMemory]);

        try {
            $processed = true; // first time do not wait for news
            while (true) {
                $this->thread = $this->threadFactory->update($this->thread, $partner);
                $this->mqSender->declarePartnerQueue($this->mqPartnerChannel, $this->thread->getPartner());
                if ($this->thread->isDedicated() || $partner !== null) {
                    $this->processMessages(60, $this->thread->getPartner(), $exceptionLogger);
                } else {
                    if (!$processed) {
                        $this->waitNews($this->thread->getPartner());
                    }
                    $processed = $this->processMessages(0, $this->thread->getPartner(), $exceptionLogger);
                    if (!$processed) {
                        $processed = $this->processMessages(0, ThreadFactory::BACKGROUND_PARTNER, $exceptionLogger);
                    }
                }
            }
        }
        catch(ExitException $e){
            $this->thread->stop();
            $this->logger->info("exiting, reason: ". $e->getMessage());
        }
        finally{
            try {
                $this->thread->stop();
            }
            catch (\Throwable $exception) {
                $this->logger->warning("failed to stop thread: ". $e->getMessage());
            }
        }
    }

}