<?php

namespace AppBundle\Extension;

use AppBundle\Event\ExtendTimeLimitEvent;
use AppBundle\Document\RetriesState;
use AwardWallet\Common\Itineraries\ItinerariesCollection;
use AwardWallet\Common\AWS\Util;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\Component\Options;
use Doctrine\DBAL\Connection;
use Monolog\Handler\PsrHandler;
use AwardWallet\Common\Geo\GoogleGeo;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CheckerFactory
{
    /** @var LoggerInterface */
    protected $logger;
    /** @var Connection */
    private $connection;
    /** @var GoogleGeo */
    private $googleGeo;
    /** @var \SeleniumConnector */
    private $seleniumConnector;
    /** @var \CurlDriver */
    private $curlDriver;
    /** @var  bool */
    private $useLastHostAsProxy;
    /** @var Util */
    private $awsUtil;
    /** @var EventDispatcherInterface */
    private $eventDispatcher;
    /** @var TimeCommunicator */
    private $time;
    /**
     * @var ContainerInterface
     */
    private $parsingServices;

    public function __construct(
        LoggerInterface $logger,
        Connection $connection,
        GoogleGeo $googleGeo,
        \SeleniumConnector $seleniumConnector,
        $useLastHost,
        Util $awsUtil,
        TimeCommunicator $time,
        EventDispatcherInterface $eventDispatcher,
        \CurlDriver $curlDriver,
        ContainerInterface $parsingServices
    ) {
        $this->logger = $logger;
        $this->connection = $connection;
        $this->googleGeo = $googleGeo;
        $this->seleniumConnector = $seleniumConnector;
        $this->curlDriver = $curlDriver;
        $this->useLastHostAsProxy = $useLastHost;
        $this->awsUtil = $awsUtil;
        $this->time = $time;
        $this->eventDispatcher = $eventDispatcher;
        $this->parsingServices = $parsingServices;
    }

    public function getAccountChecker(
        $providerCode,
        array $accountInfo = null,
        ?RetriesState $retriesState = null,
        ?int $requestDateTime = null
    ): \TAccountChecker {

        $className = "\\TAccountChecker" . ucfirst(strtolower($providerCode));
        $requestDateTime = $requestDateTime ?? time();

        return $this->buildInstance($providerCode, $className, $accountInfo, $requestDateTime, $retriesState);
    }

    public function getRewardAvailabilityChecker(
        string $providerCode,
        array $accountInfo = null,
        ?RetriesState $retriesState = null,
        ?int $requestDateTime = null
    ): \TAccountChecker {

        $className = "AwardWallet\\Engine\\{$providerCode}\\RewardAvailability\\Parser";
        $requestDateTime = $requestDateTime ?? time();
        $checker = $this->buildInstance($providerCode, $className, $accountInfo, $requestDateTime, $retriesState);
        $checker->isRewardAvailability = true;

        return $checker;
    }

    public function getRaHotelChecker(
        string $providerCode,
        array $accountInfo = null,
        ?RetriesState $retriesState = null,
        ?int $requestDateTime = null
    ): \TAccountChecker {

        $className = "AwardWallet\\Engine\\{$providerCode}\\RewardAvailability\\HotelParser";
        $requestDateTime = $requestDateTime ?? time();

        $checker = $this->buildInstance($providerCode, $className, $accountInfo, $requestDateTime, $retriesState);
        $checker->isRewardAvailability = true;

        return $checker;
    }

    public function getRewardAvailabilityRegister($providerCode) {
        $className = "AwardWallet\\Engine\\{$providerCode}\\RewardAvailability\\Register";
        if (!class_exists($className))
            $className = 'AwardWallet\\Engine\\'.$providerCode.'\\Transfer\\Register';

        $requestDateTime = time();
        $checker = $this->buildInstance($providerCode, $className, [], $requestDateTime, null);

        $checker->onTimeLimitIncreased = function (int $time) {
            $this->eventDispatcher->dispatch(ExtendTimeLimitEvent::NAME, new ExtendTimeLimitEvent($time));
        };

        $checker->ParseIts = false;
        $checker->WantHistory = false;
        $checker->HistoryStartDate = null;
        $checker->WantFiles = false;
        $checker->FilesStartDate = null;
        $checker->KeepLogs = true;
        $checker->TransferMethod = 'register';
        $checker->isRewardAvailability = true;

        if(method_exists($checker, "setSeleniumConnector"))
            $checker->setSeleniumConnector($this->seleniumConnector);

        return $checker;
    }

    private function buildInstance($providerCode, $className, ?array $accountInfo, int $requestDateTime, ?RetriesState $retriesState): \TAccountChecker
    {
        if (!class_exists($className)) {
            $className = "\\TAccountChecker";
        }
        if ($retriesState) {
            $accountInfo['State'] = $retriesState->getCheckerState();
        } elseif (isset($accountInfo['BrowserState']) && !empty($accountInfo['BrowserState'])) {
            $accountInfo['State'] = \TAccountChecker::extractState($accountInfo['BrowserState']);
        }

        if (method_exists($className, 'GetAccountChecker') && isset($accountInfo)
            && (isset($accountInfo['Login']) || strpos($className, 'RewardAvailability') !== false)
        ) {
            $checker = $className::GetAccountChecker($accountInfo);
            if (empty($checker)) {
                throw new CheckerFactoryException("Can not create checker for \"$providerCode\". GetAccountChecker is empty");
            }
        } else {
            $checker = new $className();
        }

        $sql = <<<SQL
            SELECT ProviderID, DisplayName, ShortName, ProgramName, Engine as ProviderEngine, Code, RequestsPerMinute, CanCheckItinerary, CanChangePasswordServer
            FROM Provider
            WHERE Code = :CODE
SQL;
        $provider = $this->connection->executeQuery($sql, [':CODE' => addslashes($providerCode)])->fetch();
        if (empty($provider)) {
            throw new CheckerFactoryException("Can not find provider \"$providerCode\"");
        }

        $checker->requestDateTime = $requestDateTime;
        $checker->AccountFields = $provider;
        $checker->itinerariesCollection = new ItinerariesCollection($this->googleGeo, $checker->logger);
        $checker->db = new \DatabaseHelper($this->connection);
        $checker->KeepLogs = true;
        $checker->ParseIts = (int)$provider['CanCheckItinerary'] === 1;
        $checker->globalLogger = $this->logger;
        $options = new Options();
        $options->throwOnInvalid = true;
        $options->logDebug = true;
        $options->logContext['class'] = get_class($checker);
        $checker->itinerariesMaster = new Master('itineraries', $options);
        $checker->itinerariesMaster->getLogger()->pushHandler(new PsrHandler($this->logger));
        if ($this->useLastHostAsProxy) {
            $checker->hostName = $this->awsUtil->getHostName();
            $checker->useLastHostAsProxy = true;
        }

        if (!empty($accountInfo)) {
            $checker->AccountFields = array_merge($accountInfo, $checker->AccountFields);

            $checker->onBrowserReady = function (\TAccountChecker $checker) use ($retriesState) {
                if (
                    !empty($checker->AccountFields['RequestsPerMinute'])
                    &&
                    ($checker->AccountFields['Priority'] < $checker->AccountFields['ThrottleBelowPriority'] || $checker->AccountFields['RequestsPerMinute'] < 0)
                ) {
                    $checker->http->setRequestsPerMinute($checker->AccountFields['ProviderCode'],
                        $checker->AccountFields['RequestsPerMinute']);
                    $this->logger->info('Set RequestsPerMinute = ' . $checker->AccountFields['RequestsPerMinute']);
                }
            };

            $checker->onTimeLimitIncreased = function (int $time) {
                $this->eventDispatcher->dispatch(ExtendTimeLimitEvent::NAME, new ExtendTimeLimitEvent($time));
            };
        }

        if ($retriesState) {
            $checker->onStateLoaded = function (\TAccountChecker $checker) use ($retriesState) {
                $checker->setCheckerState($retriesState->getCheckerState());

                if (!empty($retriesState->getInvalidAnswers())) {
                    foreach ($retriesState->getInvalidAnswers() as $question => $answer) {
                        unset($checker->Answers[$question]);
                    }
                }
            };
        }


        $checker->setCurlDriver($this->curlDriver);
        $checker->services = $this->parsingServices;

        return $checker;
    }

}