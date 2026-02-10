<?php

namespace AppBundle\Worker\CheckExecutor;

use AccountCheckerLoggerHandler;
use AppBundle\Document\ChangePassword;
use AppBundle\Document\BaseDocument;
use AppBundle\Document\CheckAccount;
use AppBundle\Document\RaHotel;
use AppBundle\Document\RegisterAccount;
use AppBundle\Document\RewardAvailability;
use AppBundle\Event\ParsingFinishedEvent;
use AppBundle\Extension\MQMessages\CallbackRequest;
use AppBundle\Document\CheckConfirmation;
use AppBundle\Extension\CheckerFactory;
use AppBundle\Extension\Loader;
use AppBundle\Extension\MQMessages\CheckPartnerMessage;
use AppBundle\Extension\MQSender;
use AppBundle\Extension\S3Custom;
use AppBundle\Extension\StopCheckException;
use AppBundle\Extension\TimeCommunicator;
use AppBundle\Extension\Watchdog;
use AppBundle\Model\Resources\BaseCheckRequest;
use AppBundle\Model\Resources\BaseCheckResponse;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AppBundle\Model\Resources\CheckConfirmationRequest;
use AppBundle\Model\Resources\CheckConfirmationResponse;
use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use AppBundle\Document\RetriesState;
use AppBundle\Model\Resources\RewardAvailability\Register\RegisterAccountResponse;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityResponse;
use AppBundle\Model\Resources\RewardAvailability\Route;
use AppBundle\Model\Resources\RewardAvailability\Segment;
use AppBundle\Model\Resources\UserData;
use AppBundle\Service\ApiValidator;
use AwardWallet\Common\API\Filter\Filter;
use AwardWallet\Common\AWS\AwsUtilException;
use AwardWallet\Common\AWS\Util;
use AwardWallet\Common\CurrencyConverter\CurrencyConverter;
use AwardWallet\Common\Itineraries\Cruise;
use AwardWallet\Common\Itineraries\Flight;
use AwardWallet\Common\Itineraries\FlightSegment;
use AwardWallet\Common\Parsing\Filter\ItinerariesFilter;
use AwardWallet\Common\Parsing\Solver\Exception as SolverException;
use AwardWallet\Common\Parsing\Solver\Extra\Extra;
use AwardWallet\Common\Parsing\Solver\Extra\ProviderData;
use AwardWallet\Common\Parsing\Solver\MasterSolver;
use AwardWallet\Common\Parsing\Solver\MissingDataException;
use AwardWallet\ExtensionWorker\CommunicationException;
use AwardWallet\ExtensionWorker\FileLogger;
use AwardWallet\ExtensionWorker\ParserLogger;
use AwardWallet\Schema\Parser\Component\InvalidDataException;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\Util\ArrayConverter;
use Doctrine\Common\Persistence\ObjectRepository;
use Doctrine\DBAL\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use JMS\Serializer\Exception\RuntimeException;
use JMS\Serializer\SerializerInterface;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

abstract class BaseExecutor
{

    private const EXTENSION_STATE_PREFIX = 'es_v1:';

    /** @var Logger */
    protected $logger;
    /** @var Connection */
    protected $connection;
    /** @var Connection */
    protected $shared_connection;
    /** @var DocumentManager */
    protected $manager;
    /** @var Loader */
    protected $loader;
    /** @var CheckerFactory */
    protected $factory;
    /** @var ItinerariesFilter */
    protected $itinerariesFilter;
    /** @var S3Custom */
    protected $s3Client;
    /** @var ProducerInterface */
    protected $callbackProducer;
    /** @var MQSender */
    protected $mqSender;
    /** @var ObjectRepository */
    protected $repo;
    /** @var SerializerInterface */
    protected $serializer;
    /** @var ProducerInterface */
    protected $delayedProducer;
    /** @var \Memcached */
    protected $memcached;
    /** @var ApiValidator */
    protected $validator;
    /** @var string */
    protected $aesKey;
    /** @var MasterSolver */
    protected $solver;
    /** @var EventDispatcherInterface */
    protected $eventDispatcher;
    /** @var CurrencyConverter */
    protected $currencyConverter;

    protected $RepoKey;
    protected $accountKey;
    protected $name;
    protected $uploadOnS3 = true;
    protected $backgroundCheck = false;
    protected  $requestTime;
    protected  $timeout;

    const MAX_TIME_CHECKING_WITH_IGNORE_THROTTLETIME = 60 * 60 * 24;
    const MAX_CHECKING_RETRIES = 5;
    const CHECK_DELAYED = 60;
    const LOYALTY_THROTTLED_PROVIDER = 'loyalty_throttled_%s';
    const UNKNOWN_ERROR = 'Unknown error';
    /**
     * @var Watchdog
     */
    private $watchdog;
    /**
     * @var Util
     */
    private $awsUtil;
    /**
     * @var int
     */
    private $lastTaskTime = 0;

    private TimeCommunicator $timeCommunicator;

    public function __construct(
        Logger $logger,
        Connection $connection,
        Connection $sharedConnection,
        DocumentManager $manager,
        Loader $loader,
        CheckerFactory $factory,
        S3Custom $s3Client,
        ProducerInterface $callbackProducer,
        MQSender $mqSender,
        SerializerInterface $serializer,
        ProducerInterface $delayedProducer,
        \Memcached $memcached,
        $aesKey,
        ApiValidator $validator,
        ItinerariesFilter $itinerariesFilter,
        MasterSolver $solver,
        Watchdog $watchdog,
        EventDispatcherInterface $eventDispatcher,
        Util $awsUtil,
        CurrencyConverter $currencyConverter,
        TimeCommunicator $timeCommunicator
    ) {
        $this->logger = $logger;
        $this->connection = $connection;
        $this->shared_connection = $sharedConnection;
        $this->manager = $manager;
        $this->loader = $loader;
        $this->factory = $factory;
        $this->s3Client = $s3Client;
        $this->callbackProducer = $callbackProducer;
        $this->mqSender = $mqSender;
        $this->serializer = $serializer;
        $this->delayedProducer = $delayedProducer;
        $this->aesKey = $aesKey;
        $this->validator = $validator;

        $this->name = substr(basename(str_replace('\\', '/', get_class($this))), 0, -8);
        $this->memcached = $memcached;
        $this->itinerariesFilter = $itinerariesFilter;
        $this->solver = $solver;
        $this->watchdog = $watchdog;
        $this->eventDispatcher = $eventDispatcher;
        $this->awsUtil = $awsUtil;
        $this->currencyConverter = $currencyConverter;
        $this->timeCommunicator = $timeCommunicator;
    }

    /**
     * @param \TAccountChecker $checker
     * @param CheckAccountRequest|CheckConfirmationRequest $request
     * @param BaseDocument $row
     * @param bool $fresh
     */
    abstract protected function processChecker(\TAccountChecker $checker, $request, BaseDocument $row, bool $fresh = true);

    /**
     * @param \TAccountChecker $checker
     * @param CheckAccountRequest|CheckConfirmationRequest $request
     * @param CheckAccountResponse|CheckConfirmationResponse|RewardAvailabilityResponse $response
     * @param integer $apiVersion
     */
    abstract protected function prepareResponse(\TAccountChecker $checker, $request, &$response, $apiVersion, string $partner);

    protected function prepareAccountInfo(BaseCheckRequest $request, string $partner, BaseDocument $doc) : array
    {

        $result = [
            'UserID' => $request->getUserId(),
            'Partner' => $partner,
            'Priority' => $request->getPriority(),
            'ThrottleBelowPriority' => $this->getThrottleBelowPriority($partner),
            'ProviderCode' => $request->getProvider(),
            'RequestID' => $doc->getId(),
            'Method' => $this->name,
        ];

        return $result;
    }

    protected function getThrottleBelowPriority($partner){
        $throttleBelowPriority = null;

        $sqlPartner = <<<SQL
            SELECT ThrottleBelowPriority
            FROM Partner
            WHERE Login = :PARTNER
SQL;
        $row = $this->connection->executeQuery($sqlPartner, [':PARTNER' => $partner])->fetchOne();
        if($row !== false)
            $throttleBelowPriority = $row;

        return $throttleBelowPriority;
    }


    /**
     * @param CheckAccountResponse|CheckConfirmationResponse $response
     * @param CheckAccount|CheckConfirmation $row
     */
    abstract protected function saveResponse($response, &$row);

    /**
     * @param BaseCheckRequest $request
     * @param BaseDocument $row
     * @return \TAccountChecker
     */
    protected function buildChecker($request, BaseDocument $row): \TAccountChecker {
        // old mongo rows fix
        try {
            $accountInfo = $this->prepareAccountInfo($request, $row->getPartner(), $row);
        } catch(RuntimeException $e) {
            $this->logger->notice('JSON Decode error', ["exception" => $e]);
            throw new StopCheckException();
        }
        $apiVersion = $row->getApiVersion() ?? 1;
        if ($row->getResponse()) {
            $response = $this->serializer->deserialize(json_encode($row->getResponse()), $this->getResponseClass($apiVersion), 'json');
        }
        $requestDateTime = (isset($response) && $response->getRequestdate()) ? $response->getRequestdate()->getTimestamp() : null;

        /** @var \TAccountChecker $checker */
        $checker = $this->factory->getAccountChecker($request->getProvider(), $accountInfo, $row->getRetriesState(), $requestDateTime);
        $checker->attempt = $row->getRetries();

        return $checker;
    }

    abstract protected function getRequestClass(int $apiVersion): string;

    abstract protected function getResponseClass(int $apiVersion): string;

    /**
     * @param ObjectRepository $repo
     */
    public function setMongoRepo($repo)
    {
        $this->repo = $repo;
    }

    public function execute(BaseDocument $row): void
    {
        $this->uploadOnS3 = true;
        $sendCallback = true;
        $this->logger->info("worker executor " . $this->name . " started");

        // workaround about: Error while sending QUERY packet. PID=14465
        // deprecated:
        // 'https://github.com/doctrine/dbal/pull/4119',
        // ''Retry and reconnecting lost connections now happens automatically, ping() will be removed in DBAL 3.'

        if (($this->timeCommunicator->getCurrentTime() - $this->lastTaskTime) > 60) {
            $this->logger->debug("ping mysql connection");
            $this->connection->ping();
            $this->logger->debug("ping mysql shared-connection");
            $this->shared_connection->ping();
            $this->lastTaskTime = $this->timeCommunicator->getCurrentTime();
        }

        $apiVersion = $row->getApiVersion() ?? 1;

        if ($row instanceof RewardAvailability || $row instanceof RegisterAccount || $row instanceof RaHotel) {
            $request = $row->getRequest();
            $response = $row->getResponse();
        } else {
            /** @var CheckAccountResponse|CheckConfirmationResponse $response */
            $response = $this->serializer->deserialize(json_encode($row->getResponse()), $this->getResponseClass($apiVersion), 'json');
            /** @var CheckAccountRequest|CheckConfirmationRequest $request */
            $request = $this->serializer->deserialize(json_encode($row->getRequest()), $this->getRequestClass($apiVersion), 'json');
        }

        $throttleBelowPriority = $this->getThrottleBelowPriority($row->getPartner());
        if (null === $throttleBelowPriority) {
            $this->backgroundCheck = false;
        } else {
            $this->backgroundCheck = $request->getPriority() < $throttleBelowPriority;
        }

        $this->watchdog->addContext(
            getmypid(),
            [
                'logContext' => [
                    'document' => get_class($row),
                    'requestId' => $row->getId(),
                    'userData' => $request->getUserData(),
                    'provider' => $request->getProvider(),
                    'partner' => $row->getPartner(),
                ]
            ]
        );

        $this->logger->pushProcessor(
            function(array $record) use ($row, $request) {
                $idRowName = $this->name === 'CheckAccount' ? 'userData' : 'userId';
                $record['extra'][$idRowName] = $request->getUserData();
                $record['extra']['partner'] = $row->getPartner();
                $record['extra']['provider'] = $request->getProvider();
                if (isset($this->accountKey)) {
                    $record['extra']['accountKey'] = $this->accountKey;
                }
                return $record;
            }
        );

        try {
            if ($response->getState() !== ACCOUNT_UNCHECKED) {
                $this->logger->info("processing of " . $request->getUserData() . " ignored", ['response_state' => $response->getState()]);
                // record stats, otherwise this rows will be missed
                // ??? for other
                if (($row instanceof RewardAvailability || $row instanceof RaHotel) && $row->getRetries() > 0) {
                    $this->mqSender->dumpPartnerStatistic("Check" . ucfirst($this->RepoKey), $request, $row, 0, false);
                }
                return;
            }

            if (is_null($row->getFirstCheckDate())) {
                $row->setFirstCheckDate(new \DateTime());
                $this->manager->persist($row);
                $this->manager->flush();
            }

            $queueTime = $row->getQueuedate() instanceof \DateTime ? $row->getQueuedate()->getTimestamp() : 0;
            $timeout = (int)$request->getTimeout();

            if ($timeout === 0) {
                $timeout = 86400 * 10; // timeout message after 10 days
            }
            $this->requestTime = $response->getRequestdate()->getTimestamp();
            if ($row instanceof RewardAvailability) {
                $checkTimeout = $timeout > 0 && ($this->timeCommunicator->getCurrentTime() - $this->requestTime) > $timeout;
            } else {
                $checkTimeout = $timeout > 0 && $queueTime > 0 && $queueTime + $timeout < $this->timeCommunicator->getCurrentTime();
            }
            if ($row instanceof RegisterAccount && $row->getRetries() < 5) {
                $checkTimeout = false;
            }
            if ($checkTimeout) {
                $this->checkingTimeout($row, $request, $response, "queue timeout, queued: " . date("H:i:s", $queueTime) . " with timeout $timeout");
                return;
            }
            $this->timeout = $timeout;
            if ($row->isKilled() && (!$this->backgroundCheck || $row->getKilledCounter() > 1)) {
                $this->checkingTimeout($row, $request, $response, "process timed out");
                return;
            }

            if ($this->backgroundCheck && ($this->timeCommunicator->getCurrentTime() - $this->requestTime) >= self::MAX_TIME_CHECKING_WITH_IGNORE_THROTTLETIME) {
                $this->checkingTimeout($row, $request, $response, "process timed out");
                return;
            }

        $throttleAllChecks = $this->connection->executeQuery(/** @lang MySQL */ 'select ThrottleAllChecks from Provider where Code = ?',
            [$request->getProvider()])->fetchOne();
        if ($throttleAllChecks
            && !($row instanceof RewardAvailability || $row instanceof RaHotel)
            && $this->memcached->get(sprintf(self::LOYALTY_THROTTLED_PROVIDER, $request->getProvider())) === true
        ) {
            $this->checkDelayed($row, $request->getPriority(), self::CHECK_DELAYED * 1000);
            $this->logger->notice("break because of provider throttling");
            return;
        }

            $checkingRetries = $row->getRetries();
            $checkingRetries += 1;
            $row->setRetries($checkingRetries);
            $row->setUpdatedate(new \DateTime());
            $this->manager->persist($row);
            $this->manager->flush();

            $this->logger->info("processing of " . $request->getUserData() . " started, try: " . $checkingRetries,
                ['prevParsingTime' => (int)$row->getParsingTime()]);
            if ($checkingRetries === 0 && ($row instanceof RewardAvailability || $row instanceof RaHotel || $row instanceof RegisterAccount)) {
                $this->logger->info("request data: " . $this->serializer->serialize($request, 'json'));
            }

            if ($this->retriesExceeded($request, $checkingRetries)) {
                $this->checkingTimeout($row, $request, $response, "retries exceeded({$checkingRetries})");
                return;
            }

            $startTime = $this->timeCommunicator->getCurrentTime();
            $isStopped = false;
            try {
                $this->processRequest($request, $response, $row);
            } catch (\ThrottledException $e) {
                $firstException = $e;
                while ($firstException->getPrevious() !== null) {
                    $firstException = $firstException->getPrevious();
                }

                $message = "ThrottledException: {$firstException->getMessage()} at {$firstException->getFile()}:{$firstException->getLine()}, try $checkingRetries, delay: " . $e->retryInterval . " sec";
                $this->logger->notice($message, ['countAsRetry' => $e->isCountAsRetry()]);
                if ($row instanceof RegisterAccount && strpos($e->getMessage(), 'all selenium servers are busy') === false) {
                    $this->checkingTimeout($row, $request, $response, "no auto retries: " . $firstException->getMessage());
                    return;
                }
                if (!($row instanceof RegisterAccount) && !$e->isCountAsRetry()) {
                    $checkingRetries--;
                    $row->setRetries($checkingRetries);
                    $this->manager->persist($row);
                    $this->manager->flush();
                }

                $checkingRetries++;
                $maxRetries = self::MAX_CHECKING_RETRIES;

                if ($e->getMaxRetries() !== null) {
                    $maxRetries = $e->getMaxRetries();
                }

                $maxThrottlingTime = $e->getMaxThrottlingTime();

                if ($row->getSource() === UserData::SOURCE_OPERATIONS) {
                    $maxThrottlingTime = 3600 * 3;
                    $this->logger->info("this is a request from operations, throttling time extended to {$maxThrottlingTime}");
                    $maxRetries = 1000000;
                } elseif ($this->backgroundCheck) {
                    $maxThrottlingTime = self::MAX_TIME_CHECKING_WITH_IGNORE_THROTTLETIME;
                    $this->logger->info("this is a background check, throttling time extended to {$maxThrottlingTime}");
                    $maxRetries = 1000000;
                }

                if ($checkingRetries >= $maxRetries) {
                    $this->checkingTimeout($row, $request, $response, "retries exceeded ({$checkingRetries}): {$firstException->getMessage()} at {$firstException->getFile()}:{$firstException->getLine()}");
                    return;
                }

                if ($row->getThrottledTime() >= $maxThrottlingTime) {
                    $this->checkingTimeout($row, $request, $response, "throttling time exceeded ({$maxThrottlingTime}): " . $firstException->getMessage());
                    return;
                }

                $this->checkDelayed($row, $request->getPriority(), $e->retryInterval * 1000);

                if ($e->getProvider() !== null) {
                    $this->memcached->set(sprintf(self::LOYALTY_THROTTLED_PROVIDER, $e->getProvider()), true, $e->retryInterval);
                }
                $throttledTime = $row->getThrottledTime();
                $throttledTime = isset($throttledTime) ? $throttledTime : 0;
                $row->setThrottledTime($throttledTime + $e->retryInterval);
                // for debug
                $this->logger->info("counters data",
                    [
                        'checkingRetries' => $checkingRetries,
                        'rowRetries' => $row->getRetries(),
                        'throttledTime' => $row->getThrottledTime(),
                        'asRetry' => $e->isCountAsRetry()
                    ]
                );

                $response->addDebuginfo($message);

                $sendCallback = false;
            } catch (StopCheckException $e) {
                $this->checkingTimeout($row, $request, $response, "StopCheckException: " . $e->getMessage());
                $isStopped = true;
            } finally {
                $memPrevTime = (int)$row->getParsingTime();
                $row->setParsingTime($memPrevTime + $this->timeCommunicator->getCurrentTime() - $startTime);
                $this->manager->persist($row);
                $this->manager->flush();
                $this->logger->notice("setParsingTime: " . $row->getParsingTime(), ['prevParsingTime' => $memPrevTime]);
            }

            if ($isStopped) {
                return;
            }
            $this->saveResponseGeneral($response, $row);
            if ($sendCallback) {
                $this->sendCallback($request, $row);
            }
        } finally {
            $this->logger->popProcessor();
        }
    }

    /**
     * @param CheckAccount|CheckConfirmation $row
     * @param CheckAccountRequest|CheckConfirmationRequest $request
     * @param CheckAccountResponse|CheckConfirmationResponse $response
     * @throws \Doctrine\DBAL\DBALException
     */
    private function checkingTimeout($row, $request, $response, string $reason)
    {
        $this->logger->notice("check timeout: " . $reason);
        if ($response instanceof BaseCheckResponse) {
            $response->setMessage('Timeout')
                ->setCheckdate(new \DateTime())
                ->setUserdata($request->getUserData());
        }
        if ($response instanceof RegisterAccountResponse) {
            $response->setMessage("Sorry, we couldn't complete your request. Try again later.");
        }
        if ($row instanceof RewardAvailability) {
            if (null !== $row->getAccountId() && method_exists($this, 'unLockAccount')) {
                $this->unLockAccount($row->getAccountId(), $request->getProvider(), ACCOUNT_TIMEOUT);
            }
            $response->setMessage(sprintf("too long check. over %s sec", $this->timeout));
        }
        $response->setState(ACCOUNT_TIMEOUT)
                 ->addDebuginfo($reason)
        ;

        $this->saveResponseGeneral($response, $row);
        $this->sendCallback($request, $row);
        // some delay to prevent rabbit high cpu spike
        // in case of mass timeouts
        usleep(random_int(100000, 2000000));
    }

    /**
     * @param CheckAccount|CheckConfirmation $row
     * @param $id
     * @param $partner
     * @param $priority
     * @param $interval
     */
    private function checkDelayed($row, $priority, $interval)
    {
        $message = new CheckPartnerMessage($row->getId(), $this->RepoKey, $row->getPartner(), $priority);

        $row->setThrottledTime($row->getThrottledTime() + $interval / 1000);
        $this->manager->persist($row);
        $this->manager->flush();

        $this->delayedProducer->publish($this->serializer->serialize($message, 'json'), '',
            ['application_headers' => ['x-delay' => ['I', $interval]]]);
        $this->logger->info('sent to check delayed queue', ['delayedTime' => round($interval / 1000, 0)]);
    }

    /**
     * @param CheckAccountRequest|CheckConfirmationRequest $request
     * @param CheckAccountResponse|CheckConfirmationResponse $response
     * @param CheckAccount | CheckConfirmation | ChangePassword $row
     * @throws \Doctrine\DBAL\DBALException
     */
    public function processRequest($request, $response, $row)
    {
        try {
            if ($request instanceof BrowserExtensionRequestInterface && $request->getBrowserExtensionSessionId()) {
                $this->processBrowserExtension($request, $response, $row);

                return;
            }

            $checker = $this->buildChecker($request, $row);

            // will not send BrowserState, it is too large, and sometimes badly serialized
            $accountFields = $checker->AccountFields;
            unset($accountFields['BrowserState']);
            // accountFields, logDir used in CheckWorkerKillListener
            $this->watchdog->addContext(getmypid(), ["logDir" => $checker->getLogDir(),
                "accountFields" => $accountFields, "logContext" => ["logDir" => $checker->getLogDir()]]);

            try {
                $apiVersion = $row->getApiVersion() ?? 1;
                $this->logger->pushProcessor(function (array $record) use ($checker) {
                    if (isset($record['level'])) {
                        if ($record['level'] >= Logger::WARNING) {
                            $file = '';
                            $line = '';
                            if (isset($record['context']['exception']) && $record['context']['exception'] instanceof \Exception) {
                                /** @var \Exception $exception */
                                $exception = $record['context']['exception'];
                                $file = $exception->getFile();
                                $line = $exception->getLine();
                            }
                            $checker->logger->error(sprintf("%s [%s:%s]", $record['message'], $file, $line));
                            return $record;
                        }

                        if ($record['level'] >= Logger::INFO) {
                            $checker->logger->log($record['level'], $record['message'], $record['context'] ?? []);
                            return $record;
                        }
                    }

                    return $record;
                });
                $this->accountKey = $checker->AccountFields['AccountKey'] ?? null;
                $processCheckerSuccess = false;
                try {
                    $this->processChecker($checker, $request, $row);
                    $processCheckerSuccess = true;
                } catch (\ThrottledException $e) {
                    if ($row instanceof RegisterAccount && method_exists($this, 'removeAccount')) {
                        $this->removeAccount($checker);
                    }

                    throw $e;
                } catch (\DieTraceException $e) {
                    $checker->logger->error($e->getMessage());
                    $checker->DebugInfo = $e->getMessage();
                    $checker->ErrorCode = ACCOUNT_ENGINE_ERROR;
                    $checker->ErrorMessage = 'Unknown error';
                    $processCheckerSuccess = true;
                } finally {
                    $this->accountKey = null;
                    $this->logger->popProcessor();
                    if (method_exists($this, 'unLockAccount') && isset($checker->AccountFields['AccountKey'])) {
                        $this->unLockAccount($checker->AccountFields['AccountKey'], $request->getProvider(), $checker->ErrorCode);
                    }
                    if ($processCheckerSuccess === true) {
                        $this->prepareResponse($checker, $request, $response, $apiVersion, $row->getPartner());
                    }
                }
            } finally {
                $checker->Cleanup();
                if ($row instanceof RewardAvailability || $row instanceof RegisterAccount || $row instanceof RaHotel) {
                    $response = $row->getResponse();
                }
                $this->saveLogs($response, $checker);
                $this->logProoxyInfo($checker);
                if ($checker->http !== null) {
                    $this->rrmdir($checker->http->LogDir);
                }
                $row->setCaptchaTime(intval($row->getCaptchaTime()) + $checker->getCaptchaTime());
            }
        } finally {
            $this->eventDispatcher->dispatch(ParsingFinishedEvent::NAME, new ParsingFinishedEvent());
        }
    }

    protected function processRetryException(\CheckRetryNeededException $e, \TAccountChecker $checker, BaseDocument $row)
    {
        $retryTimeout = $e->retryTimeout;
        $checker->logger->notice("[Attempt {$checker->attempt}]: Checker signalized that retry is needed from {$e->getFile()}:{$e->getLine()}");
        $needToThrow = true;
         if ($e->checkAttemptsCount <= ($checker->attempt + 1)) {
            $checker->logger->notice("Max attempts count ({$e->checkAttemptsCount}) exceeded (with interval {$retryTimeout}), no more retries");
            $this->logger->notice("Max attempts count ({$e->checkAttemptsCount}) exceeded (with interval {$retryTimeout}), no more retries");
            if ($e->errorMessageWhenAttemptsExceeded !== null) {
                $checker->ErrorMessage = $e->errorMessageWhenAttemptsExceeded;
                $checker->ErrorCode = $e->errorCodeWhenAttemptsExceeded;
            } else {
                $checker->ErrorMessage = self::UNKNOWN_ERROR;
                $checker->ErrorCode = ACCOUNT_ENGINE_ERROR;
            }
            $checker->logger->error("Error: {$e->errorMessageWhenAttemptsExceeded} [Error code: {$e->errorCodeWhenAttemptsExceeded}]");
            $needToThrow = false;
        } else {
            $checker->logger->notice("{$checker->attempt}/{$e->checkAttemptsCount} attempt failed, sleeping {$retryTimeout} seconds and trying again");

            /* save invalid answers and TAccountChecker::$State to next retry */
            $retriesState = new RetriesState($checker->InvalidAnswers, $checker->getCheckerState());
            $row->setRetriesState($retriesState);
            $this->manager->persist($row);
            $this->manager->flush();
            /* end state saving */
        }

        $checker->http->LogSplitter();
        if ($needToThrow) {
             throw new \ThrottledException($e->retryTimeout, null, $e, null, true, $e->checkAttemptsCount);
        }
    }

    /**
     * @param CheckAccountResponse|CheckConfirmationResponse $response
     * @param CheckAccount|CheckConfirmation $row
     */
    protected function saveResponseGeneral($response, &$row) {
        // debugInfo только для партнеров
        $canDebug = $this->partnerCanDebug($row->getPartner());
        if (!$canDebug) {
            $response->setDebuginfo(null);
        }

        if ($response->getState() === ACCOUNT_CHECKED) {
            $cls = basename(str_replace('\\', '/', $this->getResponseClass($row->getApiVersion())));
            $responseStdCls = json_decode($this->serializer->serialize($response, 'json'), false);
            $errors = $this->validator->validate($responseStdCls, $cls, $row->getApiVersion());
            if (!empty($errors))  {
                $this->logger->notice(
                    'Response incompatible with operation schema',
                    ['errors' => $errors]
                );

                /* TODO: uncomment after testing */
    //            $cls = $this->getResponseClass($row->getApiVersion());
    //            /** @var BaseCheckResponse $newResponse */
    //            $newResponse = new $cls();
    //            $newResponse->setRequestid($row->getId())
    //                        ->setState(ACCOUNT_TIMEOUT)
    //                        ->setMessage("Timeout")
    //                        ->setUserdata($response->getUserData())
    //                        ->setRequestdate($response->getRequestdate())
    //                        ->setCheckdate(new \DateTime());
    //            if ($canDebug) {
    //                $newResponse->setDebuginfo($e->getMessage());
    //            }
    //            $response = $newResponse;
            }
        }

        //TODO: добавить интерфейс в этом классе и имплементировать метод filterResponse() во всех воркерах
        $this->saveResponse($response, $row);
    }

    protected function partnerCanDebug($partner) : bool
    {
        return $this->connection->executeQuery(
            'SELECT CanDebug FROM Partner WHERE Login = :PARTNER',
            [':PARTNER' => $partner]
        )->fetchOne() == '1';
    }

    protected function rrmdir($dir)
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (filetype($dir . "/" . $object) == "dir") {
                        $this->rrmdir($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    protected function logLoyaltyResponse($response, LoggerInterface $logger)
    {
        $cloneResponse = clone $response;
        if (method_exists($cloneResponse, 'setBrowserstate')) {
            $cloneResponse->setBrowserstate(null);
        }

        $arrayResponse = json_decode($this->serializer->serialize($cloneResponse, 'json'), true);

        $logger->info('Loyalty Response', ['Header' => 2]);
        $logger->info(var_export($arrayResponse, true), ['pre' => true]);
    }

    protected function saveLogs($response, \TAccountChecker $checker)
    {
        $this->logLoyaltyResponse($response, $checker->logger);

        if ($checker->http !== null && $this->uploadOnS3) {
            $this->s3Client->uploadCheckerLogToBucket(
                $response->getRequestid(),
                $checker->http->LogDir,
                $checker->AccountFields,
                $this->repo
            );
        }
    }

    protected function handleAccountCheckerItineraries(\TAccountChecker $checker, int $version, string $partner) : array
    {
        $isItItem = false;

        if (!is_array($checker->Itineraries)) {
            $checker->Itineraries = [];
        }
        foreach (['Kind', "RecordLocator", "Number", "ConfirmationNumber", "ConfNo"] as $key) {
            $isItItem = array_key_exists($key, $checker->Itineraries) ? true : $isItItem;
        }

        if ($isItItem === true) {
            $checker->Itineraries = [$checker->Itineraries];
        }
        // either parser parses itineraries into objects to checker->itinerariesMaster
        // or old ways to array ->Itineraries and we're gonna convert it into objects
        if (isset($checker->itinerariesMaster)) {
            $checker->itinerariesMaster->getLogger()->pushHandler(new AccountCheckerLoggerHandler($checker->logger));
            $this->logger->pushHandler(new AccountCheckerLoggerHandler($checker->logger));
        }

        try {
            try {
                if (!empty($checker->Itineraries) && is_array($checker->Itineraries)) {
                    ArrayConverter::convertMaster(
                        ['Itineraries' => $checker->Itineraries],
                        $checker->itinerariesMaster
                    );
                    $checker->itinerariesMaster->checkValid();
                }
            } catch (InvalidDataException $e) {
                // this is thrown when an error occurred on parsed data level:
                // trying to set string value to boolean field
                // returning flight without any segments etc.
                $checker->itinerariesMaster->clearItineraries();
            }

            return $this->handleMasterItineraries($checker->itinerariesMaster, $checker->AccountFields['ProviderCode'], $version, $partner);
        }
        finally {
            if (isset($checker->itinerariesMaster)) {
                $this->logger->popHandler();
                $checker->itinerariesMaster->getLogger()->popHandler();
            }
        }

    }

    protected function getExtra(string $partner, string $providerCode) : Extra
    {
        $extra = new Extra();
        $extra->context->partnerLogin = $partner;
        $extra->provider = ProviderData::fromArray(
            $this->connection->executeQuery(
                'select ProviderID, Code, IATACode, Kind, ShortName from Provider where Code = ?',
                [$providerCode])->fetch(\PDO::FETCH_ASSOC));

        return $extra;
    }

    protected function handleMasterItineraries(Master $master, string $providerCode, int $version, string $partner) : array
    {
        if (count($master->getItineraries()) == 0) {
            return [];
        }
        // Extra object contains cache for solving itineraries and some additional info
        $extra = $this->getExtra($partner, $providerCode);
        try {
            // solver tries to fix and complete parsed data using DB and FS
            // e.g. detects addresses and stores details in Extra for futher conversion
            // corrects overnight flights dates
            // tries to solve complicated airline relationships
            $this->logger->debug("running solvers");
            $this->solver->solve($master, $extra);
        } catch (SolverException $e) {
            // solve-level exception, e.g. parser returned non-existent airport code
            // or failed to complete flight data with FS call
            $this->logger->notice($e->getMessage(), ['component' => 'parser', 'method' => 'solve']);
            return [];
        } catch (MissingDataException $e) {
            $this->logger->notice($e->getMessage(), ['component' => 'parser', 'method' => 'solve']);
            return [];
        } catch (InvalidDataException $e) {
            $this->logger->notice($e->getMessage(), ['component' => 'parser', 'method' => 'solve']);
            return [];
        }
        if ($master->getNoItineraries()) {
            return [];
        }

        switch($version) {
            // at this point itinerariesMaster and Extra contains all necessary data
            // handleItineraries only have to compose response objects
            case 1:
                return $this->handleItinerariesV1($master, $extra);
                break;
            case 2:
                return $this->handleItinerariesV2($master, $extra);
                break;
        }
        return [];
    }

    protected function handleItinerariesV2(Master $master, Extra $extra)
    {
        $loader = new \AwardWallet\Common\API\Converter\V2\Loader();
        $result = [];
        foreach ($master->getItineraries() as $itinerary) {
            $result[] = $loader->convert($itinerary, $extra);
        }
        $result = (new Filter())->filter($result);
        return $result;
    }

    /**
     * @param \TAccountChecker $checker
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function handleItinerariesV1(Master $master, Extra $extra)
    {
        $loader = new \AppBundle\VersionConverter\ItineraryConverter\Loader($this->logger);
        $result = [];
        foreach ($master->getItineraries() as $itinerary) {
            $result[] = $loader->convert($itinerary, $extra);
        }

        // Filter trips
        for ($i = 0; $i < count($result); $i++) {
            $item = $result[$i];
            if (!$item instanceof Flight && !$item instanceof Cruise) {
                continue;
            }

            for ($j = 0; $j < count($item->segments); $j++) {
                /** @var FlightSegment $segment */
                $segment = $item->segments[$j];

                if ($segment->airlineName !== null && in_array(strlen($segment->airlineName), [2, 3])) {
                    $field = strlen($segment->airlineName) == 2 ? "Code" : "ICAO";
                    $sql = "SELECT AirlineID, Name, Active FROM Airline WHERE {$field} = :CODE ORDER BY Active DESC";
                    $airlineRow = $this->connection->executeQuery($sql, [':CODE' => $segment->airlineName])->fetch();
                    if ($airlineRow) {
                        $segment->airlineName = $airlineRow['Name'];
                        if (!$airlineRow['Active']) {
                            $this->logger->warning("Found an airline name by code, but it's inactive. Airline: {$airlineRow['Name']} ({$airlineRow['AirlineID']}), Code: $segment->airlineName");
                        }
                    }
                }
            }
        }

//          TODO: Разобраться со всеми фильтрами
//        if (!empty($result)) {
//            $this->itinerariesFilter->filter($result, $checker->AccountFields['Code']);
//        }
        return $result;
    }

    /**
     * @param BaseCheckRequest $request
     * @param CheckAccount|CheckConfirmation $row
     */
    public function sendCallback(LoyaltyRequestInterface $request, $row)
    {

        if (empty($request->getCallbackUrl())) {
            $this->mqSender->dumpPartnerStatistic("Check" . ucfirst($this->RepoKey), $request, $row, 0, false);
            return;
        }

        $sql = <<<SQL
              SELECT PacketPriority, PacketDelay FROM Partner
              WHERE Login = :LOGIN
SQL;
        $result = $this->connection->executeQuery($sql, [':LOGIN' => $row->getPartner()])->fetch();
        if (!$result) {
            $this->logger->critical('Unavailable partner');
            return;
        }

        $inCallbackQueue = false;
        if ($request->getPriority() > $result['PacketPriority'] || !$this->supportPackageCallback()) {
            /* single callback */
            $this->logger->info("sending callback to queue", ['URL' => $request->getCallbackUrl()]);

            $callback = new CallbackRequest();
            $callback->setMethod($this->RepoKey)
                     ->setIds([$row->getId()])
                     ->setPartner($row->getPartner())
                     ->setPriority($request->getPriority());

            $this->callbackProducer->publish(serialize($callback), '', ['priority' => $callback->getPriority()]);
            $inCallbackQueue = true;
        } else {
            /* package callback */
            $this->logger->info("will send via package");
            $row->setIsPackageCallback(true);
            if ($row->isCancelled()) {
                $this->logger->info("will not send callback, cancelled by checker");
                $inCallbackQueue = true; // it's actually means "callback was sent", wtf
                // record stats, otherwise this rows will be missed
                $this->mqSender->dumpPartnerStatistic("Check" . ucfirst($this->RepoKey), $request, $row, 0, false);
            }
        }

        $row->setInCallbackQueue($inCallbackQueue);
        $this->manager->persist($row);
        $this->manager->flush();
    }

    protected function supportPackageCallback()
    {
        return false;
    }

    protected function logServerInfo(LoggerInterface $logger)
    {
        try {
            $logger->info("Server info > hostname: " . gethostname() . ", local ip: " . $this->awsUtil->getLocalIP());
        }
        catch (AwsUtilException $e) {
            $logger->warning("failed to detect aws params, local mode? " . $e->getMessage());
        }
    }

    private function logProoxyInfo(\TAccountChecker $checker)
    {
        $proxyAddress = $proxyRegion = $proxyProvider = null;
        $seleniumServer = $seleniumBrowserFamily = $seleniumBrowserVersion = null;
        if ($checker->http !== null) {
            $proxyAddress = $checker->getProxyIpFromState() ?? $checker->http->getProxyAddress();
            $proxyProvider = $checker->http->getProxyProvider();
            $proxyRegion = $checker->http->getProxyRegion();
            $seleniumServer = $checker->http->getSeleniumServer();
            $seleniumBrowserFamily = $checker->http->getSeleniumBrowserFamily();
            $seleniumBrowserVersion = $checker->http->getSeleniumBrowserVersion();
        }

        $statistic = [
            'retryNumber' => $checker->attempt,
            'seleniumServer' => $seleniumServer,
            'seleniumBrowserFamily' => $seleniumBrowserFamily,
            'seleniumBrowserVersion' => $seleniumBrowserVersion,
            'proxyAddress' => $proxyAddress,
            'proxyProvider' => $proxyProvider,
            'proxyRegion' => $proxyRegion,
            'proxyAddressOnInit' => $checker->proxyAddressOnInit,
            'proxyProviderOnInit' => $checker->proxyProviderOnInit,
            'proxyRegionOnInit' => $checker->proxyRegionOnInit,
            'errorCode' => $checker->ErrorCode
        ];
        $this->logger->info("proxy statistic", $statistic);
    }

    protected function runBrowserExtension(BaseCheckRequest $request, BaseCheckResponse $response, ParserLogger $parserLogger, array $state, int $apiVersion, string $partner)
    {
        throw new \Exception("not implemented");
    }

    private function extractExtensionState(string $browserState) : array
    {
        if (substr($browserState, 0, strlen(self::EXTENSION_STATE_PREFIX)) !== self::EXTENSION_STATE_PREFIX) {
            return [];
        }

        return json_decode(substr($browserState, strlen(self::EXTENSION_STATE_PREFIX)), true);
    }

    private function processBrowserExtension(BrowserExtensionRequestInterface $request, \AppBundle\Model\Resources\V2\CheckAccountResponse $response, BaseDocument $row) : void
    {
        $parserLogger = new ParserLogger($this->logger);
        try {
            $this->logger->info("running with browser extension, session id: " . $request->getBrowserExtensionSessionId());
            $response->setState(ACCOUNT_ENGINE_ERROR);
            $response->setMessage(self::UNKNOWN_ERROR);
            $state = $this->extractExtensionState($this->decodeBrowserState($request));
            try {
                $this->runBrowserExtension($request, $response, $parserLogger, $state, $row->getApiVersion() ?? 1, $row->getPartner());
            }
            catch(CommunicationException $e) {
                $this->logger->notice($e->getMessage());
            }
            $this->logLoyaltyResponse($response, $this->logger);
            $this->s3Client->uploadCheckerLogToBucket($row->getId(), $parserLogger->getLogDir(), array_merge(['Code' => $request->getProvider()], $request->getMaskedFields()), $this->repo);
        } finally {
            $parserLogger->cleanup();
        }

    }

    protected function retriesExceeded($request, int $checkingRetries) : bool
    {
        if ($request instanceof BrowserExtensionRequestInterface && $request->getBrowserExtensionSessionId() && $checkingRetries > 0) {
            return true;
        }

        return !$this->backgroundCheck && $checkingRetries >= self::MAX_CHECKING_RETRIES;
    }


}
