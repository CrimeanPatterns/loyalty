<?php


namespace AppBundle\Command;


use AppBundle\Extension\HttpCallbackSender;
use AppBundle\Extension\SendToAW;
use Monolog\Logger;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SendToAWCommand extends Command
{
    protected static $defaultName = 'aw:send-ra-to-aw';

    private const MAX_COUNT = 500;
    private $startTime;
    private $limit;
    private $maxCount;

    /** @var Logger */
    private $logger;
    /** AMQPChannel */
    private $mqChanel;
    /** @var HttpCallbackSender */
    private $httpCallbackSender;

    private $callBackUrl;

    public function __construct(
        LoggerInterface $logger,
        AMQPChannel $mqChanel,
        HttpCallbackSender $httpCallbackSender,
        $callBackUrl
    ) {
        parent::__construct();

        $this->logger = $logger;
        $this->mqChanel = $mqChanel;
        $this->httpCallbackSender = $httpCallbackSender;
        $this->callBackUrl = $callBackUrl;
    }

    public function configure()
    {
        $this->setDescription("Check data from queue send_to_aw and send it");
        $this->setDefinition([
            new InputOption(
                'limit',
                'l',
                InputOption::VALUE_OPTIONAL,
                "sets an execution time limit in minutes"
            ),
            new InputOption(
                'maxCount',
                'c',
                InputOption::VALUE_OPTIONAL,
                "sets an max count RAFlight in batch. (default is " . self::MAX_COUNT . ')'
            ),
        ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (empty($this->callBackUrl)) {
            $this->logger->info("running SendToAW stopped: callBackUrl is empty");
            return 0;
        }
        $this->limit = $input->getOption('limit');
        if (!empty($this->limit)) {
            $this->limit *= 60;  // to seconds
        }
        $this->maxCount = $input->getOption('maxCount');
        if (empty($this->maxCount)) {
            $this->maxCount = self::MAX_COUNT;
        }

        $this->startTime = time();
        $this->logger->info("SendToAW started");

        if (!$this->runSender()) {
            $this->logger->warning('SendToAW stopped: time limit exceeded');
        }

        return 0;
    }

    private function runSender(): bool
    {
        while ((empty($this->limit) || (time() - $this->startTime) < $this->limit)) {
            $messages = [];
            $data = [];
            for ($n = 0; $n < $this->maxCount; $n++) {
                /** @var AMQPMessage $message */
                $message = $this->mqChanel->basic_get(SendToAW::QUEUE_NAME);
                if (!$message) {
                    break;
                }
                $messages[] = $message;
                $data[] = $message->getBody();
            }
            if (count($data) === 0) {
                $this->logger->info("send to aw: empty queue. sending stopped");
                $isEmpty = true;
                break;
            }
            $acked = false;
            try {
                $callbackResult = $this->httpCallbackSender->sendCallback(
                    'awardwallet',
                    $this->callBackUrl,
                    serialize($data)
                );
                if ($callbackResult) {
                    foreach ($messages as $message) {
                        $this->mqChanel->basic_ack($message->delivery_info['delivery_tag']);
                    }
                    $acked = true;
                }
            } finally {
                if (!$acked) {
                    $this->logger->info("send to aw: nacking messages");
                    foreach ($messages as $message) {
                        $this->mqChanel->basic_nack($message->delivery_info['delivery_tag'], false, true);
                    }
                }
            }
            if (!$acked) {
                $this->logger->notice("send to aw: something went wrong. sending stopped");
                break;
            }
        }

        return isset($isEmpty) || (!empty($this->limit) && (time() - $this->startTime) < $this->limit);
    }
}