<?php


namespace AppBundle\Worker\CheckExecutor;


use AppBundle\Document\BaseDocument;
use AppBundle\Document\RaAccount;
use AppBundle\Document\RaAccountAnswer;
use AppBundle\Document\RaHotel;
use AppBundle\Document\RewardAvailability;
use AppBundle\Extension\CheckerFactory;
use AppBundle\Extension\Loader;
use AppBundle\Extension\MQSender;
use AppBundle\Extension\S3Custom;
use AppBundle\Extension\SendToAW;
use AppBundle\Extension\StopCheckException;
use AppBundle\Extension\TimeCommunicator;
use AppBundle\Extension\Watchdog;
use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use AppBundle\Model\Resources\RewardAvailability\Payments;
use AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelRequest;
use AppBundle\Model\Resources\RewardAvailability\RaHotel\RaHotelResponse;
use AppBundle\Model\Resources\RewardAvailability\Redemptions;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityRequest;
use AppBundle\Model\Resources\RewardAvailability\RewardAvailabilityResponse;
use AppBundle\Model\Resources\RewardAvailability\Route;
use AppBundle\Model\Resources\RewardAvailability\Segment;
use AppBundle\Model\Resources\RewardAvailability\SegmentPoint;
use AppBundle\Model\Resources\RewardAvailability\Times;
use AppBundle\Service\ApiValidator;
use AppBundle\Service\BrowserStateFactory;
use AppBundle\Service\Otc\Cache;
use AppBundle\Worker\ExitException;
use AwardWallet\Common\Airport\AirportTime;
use AwardWallet\Common\AWS\Util;
use AwardWallet\Common\CurrencyConverter\CurrencyConverter;
use AwardWallet\Common\DateTimeUtils;
use AwardWallet\Common\Document\HotSession;
use AwardWallet\Common\OneTimeCode\ProviderQuestionAnalyzer;
use AwardWallet\Common\Parsing\Filter\ItinerariesFilter;
use AwardWallet\Common\Parsing\Solver\Extra\Extra;
use AwardWallet\Common\Parsing\Solver\Helper\ALHelper;
use AwardWallet\Common\Parsing\Solver\Helper\DateCorrector;
use AwardWallet\Common\Parsing\Solver\Helper\FlightHelper;
use AwardWallet\Common\Parsing\Solver\Helper\FSHelper;
use AwardWallet\Common\Parsing\Solver\MasterSolver;
use AwardWallet\ExtensionWorker\ClientFactory;
use AwardWallet\ExtensionWorker\ParserFactory;
use AwardWallet\ExtensionWorker\ParserRunner;
use AwardWallet\ExtensionWorker\ProviderInfoFactory;
use AwardWallet\ExtensionWorker\SessionManager;
use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use JMS\Serializer\SerializerInterface;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class RewardAvailabilityExecutor extends CheckAccountExecutor
{

    protected $RepoKey = RewardAvailability::METHOD_KEY;
    public const KEY_VALID_ROUTES = 'ra_validRoutes_%s';
    private const REST_TIME_PREVENT_LOCKOUT = 60 * 60 * 3; // 3 hours

    public $refreshTime = DateTimeUtils::SECONDS_PER_HOUR;

    /** @var  BrowserStateFactory */
    private $bsFactory;

    /** @var AirportTime */
    private $airportTime;

    /** @var Cache  */
    private $otcCache;

    /** @var int */
    private $otcWaitTime;

    /** @var FSHelper */
    private $fsh;

    /** @var ALHelper */
    private $alh;

    /** @var FlightHelper */
    private $fh;

    /** @var SendToAW */
    private $toAW;

    private string $parseMode;

    public function __construct(
        LoggerInterface $logger,
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
        TimeCommunicator $timeCommunicator,
        BrowserStateFactory $browserState,
        AirportTime $airportTime,
        Cache $otcCache,
        $otcWaitTime,
        FSHelper $fsh,
        FlightHelper $fh,
        SendToAW $toAW,
        string $parseMode,
        ParserFactory $parserFactory,
        ParserRunner $parserRunner,
        ClientFactory $clientFactory,
        ProviderInfoFactory $providerInfoFactory
    ) {
        parent::__construct($logger, $connection, $sharedConnection, $manager, $loader, $factory, $s3Client, $callbackProducer, $mqSender,
            $serializer, $delayedProducer, $memcached, $aesKey, $validator, $itinerariesFilter, $solver, $watchdog,
            $eventDispatcher, $awsUtil, $currencyConverter, $timeCommunicator, $parserFactory, $parserRunner, $clientFactory, $providerInfoFactory);
        $this->bsFactory = $browserState;
        $this->airportTime = $airportTime;
        $this->otcCache = $otcCache;
        $this->otcWaitTime = $otcWaitTime;
        $this->fsh = $fsh;
        $this->fh = $fh;
        $this->toAW = $toAW;
        $this->parseMode = $parseMode;
    }

    public function processRequest($request, $response, $row)
    {
        BaseExecutor::processRequest($request, $response, $row);
    }

    public function processChecker(\TAccountChecker $checker, $request, BaseDocument $row, $fresh = true)
    {
        parent::processChecker($checker, $request, $row, $fresh);
        if ((ACCOUNT_LOCKOUT === $checker->ErrorCode || ACCOUNT_PREVENT_LOCKOUT === $checker->ErrorCode)
            && !empty($checker->AccountFields['AccountKey'])
            && ($account = $this->manager->getRepository(RaAccount::class)->find($checker->AccountFields['AccountKey']))
        ) {
            $account->setErrorCode($checker->ErrorCode);
            $this->manager->flush();
        }
        if (ACCOUNT_QUESTION === $checker->ErrorCode
            && !empty($checker->Question)
            && ProviderQuestionAnalyzer::isQuestionOtc($request->getProvider(), $checker->Question)
            && !empty($checker->AccountFields['AccountKey'])) {
            if (($account = $this->manager->getRepository(RaAccount::class)->find($checker->AccountFields['AccountKey']))) {
                $account->setErrorCode($checker->ErrorCode);
                $account->setQuestion($checker->Question);
                $this->manager->flush();
                if (($account->getWarmedUp() !== RaAccount::WARMUP_LOCK && 'skywards' === $account->getProvider())
                    || ('skywards' !== $account->getProvider() && $account->getLockState() !== RaAccount::PARSE_LOCK)) {
                    $this->logger->info('preventing multiple otc submissions', [
                        'accWarmedUp' => $account->getWarmedUp(),
                        'accProvider' => $account->getProvider(),
                        'accLockState' => $account->getLockState(),
                        'accQuestion' => $checker->Question
                    ]);
                    return;
                }
                if ($this->otcCache->isLocked($account->getId())) {
                    $this->logger->info('account is otc locked', ['accountKey' => $account->getId()]);
                    return;
                }
                $this->logger->info('ra account otc question, updated', ['accountKey' => $account->getId(), 'errorCode' => $checker->ErrorCode, 'provider' => $account->getProvider()]);
            }
            $mark = time();
            $this->logger->info(sprintf('otc question detected, waiting for it, max %d seconds', $this->otcWaitTime), ['accountKey' => $checker->AccountFields['AccountKey']]);
            while(time() - $mark < $this->otcWaitTime) {
                if ($this->otcCache->isLocked($checker->AccountFields['AccountKey'])) {
                    $this->logger->info('account was otc locked', ['accountKey' => $checker->AccountFields['AccountKey']]);
                    return;
                }
                if ($otc = $this->otcCache->getOtc($checker->AccountFields['AccountKey'])) {
                    $this->logger->info('otc found', ['accountKey' => $checker->AccountFields['AccountKey'], 'duration' => time() - $mark]);
                    $checker->Answers[$checker->Question] = $otc;
                    $this->otcCache->deleteOtc($checker->AccountFields['AccountKey']);
                    $checker->ErrorCode = ACCOUNT_ENGINE_ERROR;
                    $checker->ErrorMessage = 'Unknown Error';
                    parent::processChecker($checker, $request, $row, false);
                    return;
                }
                sleep(1);
            }
            $this->logger->info('timed out waiting for otc', ['accountKey' => $checker->AccountFields['AccountKey']]);
        }
    }

    protected function buildChecker($request, BaseDocument $row): \TAccountChecker
    {
        /** @var RewardAvailabilityRequest|RaHotelRequest $request */
        /** @var RewardAvailability|RaHotel $row */
        /** @var RewardAvailabilityResponse|RaHotelResponse $response */

        $response = $row->getResponse();
        $requestDateTime = $response->getRequestdate() ? $response->getRequestdate()->getTimestamp() : time();

        $provider = $request->getProvider();

        $debugMode = false;
        if (preg_match("/\bawDebug\b/", $request->getUserData())) {
            $debugMode = true;
        }
        $accountInfo = [
            'UserID' => '',//$request->getUserId(),
            'Partner' => $row->getPartner(),
            'Priority' => $request->getPriority(),
            'ThrottleBelowPriority' => $this->getThrottleBelowPriority($row->getPartner()),
            'ProviderCode' => $provider,
            'RequestID' => $row->getId(),
            'Method' => $row::METHOD_KEY,
            'AccountID' => '',//$accountId,
            'DebugState' => $debugMode,
            'Timeout' => $this->timeout,
            'ParseMode' => $this->parseMode
        ];
        $userData = @json_decode($request->getUserData(), true);
        $checkParseLock = $this->checkLockRAAccount($provider);

        $exceptLockout = $row->getRetries() > 0;
        if (!empty($userData['accountKey'])) {
            $account = $this->pickAccount($provider, $checkParseLock, $userData['accountKey'], $debugMode,
                $exceptLockout);
            if ($account && $account->getErrorCode() === ACCOUNT_PREVENT_LOCKOUT) {
                if ($account->getLastUseDate()->getTimestamp() < (time() - self::REST_TIME_PREVENT_LOCKOUT)) {
                    $this->logger->info('pickAccount found and skip (prevent lockout)', [
                        'accountKey' => $account->getId(),
                        'errorCode' => $account->getErrorCode(),
                        'lockState' => $account->getLockState(),
                        'accState' => $account->getState(),
                        'warmUp' => $account->getWarmedUp()
                    ]);
                    $account = null;
                } else {
                    $this->markAccountIsInUse($account, $checkParseLock);
                }
            }
            if (null === $account) {
                throw new StopCheckException('accountKey not found or account is locked');
            }
        } else {
            // TODO: need optimization
            if (in_array($provider, ['skywards', 'emirates', 'british'])) { // reset $accountKey
                if ($row->getAccountId()!==null && method_exists($this, 'unLockAccount') && isset($checker->AccountFields['AccountKey'])) {
                    $this->unLockAccount($row->getAccountId(), $request->getProvider(), ACCOUNT_UNCHECKED);
                }
                $accountKey = null;
            } else {
                $accountKey = $row->getAccountId();
            }
            $account = $this->pickAccount($provider, $checkParseLock, $accountKey, $debugMode);
            if ($accountKey && $account && $account->getErrorCode() === ACCOUNT_PREVENT_LOCKOUT) {
                if ($account->getLastUseDate()->getTimestamp() > (time() - self::REST_TIME_PREVENT_LOCKOUT)) {
                    $this->logger->info('pickAccount found and skip (prevent lockout)', [
                        'accountKey' => $account->getId(),
                        'errorCode' => $account->getErrorCode(),
                        'lockState' => $account->getLockState(),
                        'accState' => $account->getState(),
                        'warmUp' => $account->getWarmedUp()
                    ]);
                    $account = $this->pickAccount($provider, $checkParseLock, null, $debugMode);
                } else {
                    $this->markAccountIsInUse($account, $checkParseLock);
                }
            }

            if ($exceptLockout && $account && $account->getErrorCode() === ACCOUNT_LOCKOUT) {
                $row->setAccountId(null);
                $account = $this->pickAccount($provider, $checkParseLock, $row->getAccountId(), $debugMode, $exceptLockout);
                if (null === $account) {
                    throw new StopCheckException('no active accounts');
                }
            }
        }
        if (null === $account && $checkParseLock) {
            $delay = random_int(10, 40);
            if ($row instanceof RewardAvailability && (time() - $requestDateTime > $this->timeout + $delay)) {
                throw new StopCheckException(
                    sprintf("all RA-accounts {$provider} are busy. no time to wait %s sec. too long check. will be over %s sec", $delay, $this->timeout)
                );
            }
            throw new \ThrottledException($delay, $this->timeout, null, "all RA-accounts {$provider} are busy", false);
        }
        $answers = [];
        if ($account) {
            if ($checkParseLock && $account->getPass() === null) {
                // for debug
                $this->logger->notice('check getting account', [
                    'accountKey' => $account->getId(),
                    'errorCode' => $account->getErrorCode(),
                    'lockState' => $account->getLockState(),
                    'accState' => $account->getState(),
                    'warmUp' => $account->getWarmedUp(),
                    'pass' => $account->getPass()
                ]);
                $account = $this->pickAccount($provider, false, $account->getId());
                // for debug
                $this->logger->notice('check 2 getting account', [
                    'accountKey' => $account->getId(),
                    'errorCode' => $account->getErrorCode(),
                    'lockState' => $account->getLockState(),
                    'accState' => $account->getState(),
                    'warmUp' => $account->getWarmedUp(),
                    'pass' => $account->getPass()
                ]);
                if ($account->getPass() === null)
                throw new \ThrottledException(3, $this->timeout, null, "something wrong with getting RA-accounts {$provider}",
                    false);
            }
            $accountInfo = array_merge($accountInfo, array_filter([
                'AccountKey' => $account->getId(),
                'Login' => $account->getLogin(),
                'Login2' => $account->getLogin2(),
                'Login3' => $account->getLogin3(),
                'Pass' => $this->manager->getRepository(RaAccount::class)->decodePassword($account->getPass(), $this->aesKey),
            ]));
            $accountInfo['DebugState'] = $debugMode || $account->getState() === RaAccount::STATE_DEBUG;
            $row->setAccountId($account->getId());
            $this->manager->persist($row);
            $this->manager->flush();
            $state = $this->bsFactory->load($account->getId());
            if (!empty($state)) {
                $accountInfo['BrowserState'] = $state;
            }
            foreach($account->getAnswers() as $ans) {
                $answers[$ans->getQuestion()] = $ans->getAnswer();
            }
            if (ACCOUNT_QUESTION === $account->getErrorCode()
                && !empty($account->getQuestion())
                && ProviderQuestionAnalyzer::isQuestionOtc($account->getProvider(), $account->getQuestion())
                && ($otc = $this->otcCache->getOtc($account->getId()))
                && !$this->otcCache->isLocked($account->getId())) {
                $answers[$account->getQuestion()] = $otc;
                $this->logger->info('using saved otc', ['accountKey' => $account->getId(), 'provider' => $account->getProvider()]);
                $this->otcCache->deleteOtc($account->getId());
            }
        }

        if ($request instanceof RaHotelRequest) {
            $checker = $this->factory->getRaHotelChecker($provider, $accountInfo, null, $requestDateTime);
        } else {
            $checker = $this->factory->getRewardAvailabilityChecker($provider, $accountInfo, null, $requestDateTime);
        }
        $requestFields = $this->fillRequestFields($request);
        $checker->attempt = $row->getRetries();
        if (!empty($answers)) {
            $checker->Answers = $answers;
        }

        $checker->AccountFields['RaRequestFields'] = $requestFields;
        $checker->onLoggedIn = function () use ($checker, $row, $provider, $account) {
            try {
                $result = $checker->ParseRewardAvailability($checker->AccountFields['RaRequestFields']);
                if (is_array($result) && $checker->ErrorCode !== ACCOUNT_WARNING) {
                    $checker->ErrorCode = ACCOUNT_CHECKED;
                }
                $this->processResult($row, $result, $checker, $provider);
                if ($account
                    && (in_array($provider, ['skywards', 'emirates'])
                        || strpos($provider, 'skywards') === 0) // for tests
                    && isset($checker->Answers['rememberNew'])
                ) {
                    $this->updateAccountAnswers($account, $checker->Answers);
                }
            } catch (\Exception $e) {
                throw $e;
            }
        };

        return $checker;
    }

    protected function fillRequestFields($request)
    {
        $depDate = $request->getDeparture()->getDate();
        return [
            'DepCode' => strtoupper(trim($request->getDeparture()->getAirportCode())),
            'ArrCode' => strtoupper(trim($request->getArrival())),
            'Cabin' => $request->getCabin(),
            'Currencies' => [$request->getCurrency()],
            'Adults' => $request->getPassengers()->getAdults(),
            'DepDate' => $depDate instanceof DateTime ? $depDate->getTimestamp() : time(),
            'Range' => $request->getDeparture()->getFlexibility(),
            'Timeout' => $this->timeout
        ];
    }

    private function updateAccountAnswers(RaAccount $account, array $answers)
    {
        // save cookies for emirate as answers. rememberNew->remember, SSOUserNew->SSOUser...
        $answersOriginal = [];
        foreach ($account->getAnswers() as $ans) {
            $answersOriginal[$ans->getQuestion()] = $ans->getAnswer();
        }
        if (isset($answersOriginal['lastUpdateDate'])
            && strtotime($answersOriginal['lastUpdateDate']) + $this->refreshTime > time()
        ) {
            return;
        }
        unset($answersOriginal['lastUpdateDate']);
        $namesUpdCookies = [];
        foreach ($answers as $question => $answer) {
            if (preg_match("/^(?<name>\w+)New$/", $question, $m)) {
                if (!isset($answersOriginal[$m['name']])) {
                    $answersOriginal[$m['name']] = $answer;
                }
                $namesUpdCookies[] = $m['name'];
            }
        }
        foreach ($account->getAnswers() as $answer) {
            $this->manager->remove($answer);
        }
        $account->setAnswers([]);

        foreach ($answersOriginal as $question => $answer) {
            if (strlen($question) > 0 && strlen($answer) > 0) {
                $questionNew = $question . 'New';
                if (in_array($question, $namesUpdCookies)
                    && isset($answers[$questionNew]) && strlen($answers[$questionNew]) > 0
                ) {
                    $account->addAnswer(new RaAccountAnswer($question, $answers[$questionNew]));
                } else {
                    $account->addAnswer(new RaAccountAnswer($question, $answer));
                }
            }
        }
        $account->addAnswer(new RaAccountAnswer('lastUpdateDate', date('Y-m-d H:i:s')));
        $this->manager->flush();
    }

    /**
     * @param RewardAvailability|RaHotel $row
     */
    protected function processResult(BaseDocument $row, $result, \TAccountChecker $checker, string $provider)
    {
        $extra = new Extra();
        $extra->context->partnerLogin = $row->getPartner();
        $currencyIn = $checker->AccountFields['RaRequestFields']['Currencies'][0];
        $depDateIn = $checker->AccountFields['RaRequestFields']['DepDate'];

        $rate = $this->getRate($result, $currencyIn);
        if (null === $rate) {
            $checker->ErrorCode = ACCOUNT_WARNING;
            $errorMessages = [];
            if ($checker->ErrorMessage !== parent::UNKNOWN_ERROR) {
                $errorMessages[] = $checker->ErrorMessage;
            }
            $errorMessages[] = sprintf('The currency you requested (%s) is not supported', $currencyIn);
            $checker->ErrorMessage = implode('; ', $errorMessages);
            $rate = 1;
        }
        $parseErrors = [];
        $parseNotices = [];

        if (empty($result['routes']) && $checker->ErrorCode === ACCOUNT_CHECKED) {
            $checker->ErrorCode = ACCOUNT_ENGINE_ERROR;
            $checker->ErrorMessage = "Empty result cannot have status equal to 1";
            return;
        }

        if (!empty($result['routes']) && $checker->ErrorCode === ACCOUNT_CHECKED) {
            $requestFields = $checker->AccountFields['RaRequestFields'];
            $validRoute = $requestFields['DepCode'] . '-' . $requestFields['ArrCode'];
            $validRoutes = $this->memcached->get(sprintf(self::KEY_VALID_ROUTES, $provider));
            if (!$validRoutes || !is_array($validRoutes)) {
                $validRoutes = [];
            }
            if (!in_array($validRoute, $validRoutes)) {
                $validRoutes[] = $validRoute;
            }
            // save to memcached valid route for RASenderCommand
            $this->memcached->set(sprintf(self::KEY_VALID_ROUTES, $provider), $validRoutes, DateTimeUtils::SECONDS_PER_DAY * 7);
        }
        // check depDate of result

        $isCollected = !empty($result['routes']);
        if ($isCollected) {
            $firstRoute = array_values($result['routes'])[0];
            $firstSegment = array_values($firstRoute['connections'])[0];
            $depDateInStr = date('Y-m-d', $depDateIn);
            $firstSegmentDepart = date('Y-m-d', strtotime($firstSegment['departure']['date']));
            $today = date('Y-m-d');
            // +/-1 day
            $validDays = [
                $depDateInStr,
                date('Y-m-d', strtotime("-1 day", $depDateIn)),
                date('Y-m-d', strtotime("+1 day", $depDateIn))
            ];
            if (!in_array($firstSegmentDepart, $validDays)) {
                $parseErrors[] = [
                    "property" => "routes[0].connections[0].departure.date",
                    "message" => "wrong departure"
                ];
            } elseif ($depDateInStr !== $today && $depDateInStr !== $firstSegmentDepart) {
                // log to kibana just this type notices
                $this->logger->notice('Reward availability has parse notice', ['component' => 'parser']);
                $parseNotices[] = [
                    "property" => "routes[0].connections[0].departure.date",
                    "message" => "other departure"
                ];
            }
        }
        $currencies = [];
        $routes = array_map(function (array $route, int $numRoute) use($rate, $currencyIn, &$parseErrors, $extra, &$parseNotices, &$currencies) {
            if (!isset($route['redemptions']['miles']) or empty($route['redemptions']['miles'])) {
                $parseErrors[] = [
                    "property" => "routes[{$numRoute}].redemptions.miles",
                    "message" => "empty"
                ];
            }

            $currency = $route['payments']['currency'] ?? null;
            $currencies[] = $currency;
            $taxes = isset($route['payments']['taxes']) ? $route['payments']['taxes'] : null;
            $fees = isset($route['payments']['fees']) ? $route['payments']['fees'] : null;
            if ($rate != 1) {
                $originalCurrency = $currency;
                $currency = $currencyIn;
                $conversionRate = $rate;
            }
            if (isset($taxes)) {
                $taxes = round($rate * $taxes, 2);
            }
            if (isset($fees)) {
                $fees = round($rate * $fees, 2);
            }

            list($segments, $totalFlight, $totalLayover, $errors, $notices) = $this->parseSegments($route['connections'] ?? [], $numRoute, $extra);
            $parseErrors += $errors;
            $parseNotices += $notices;
            if (!isset($totalFlight)) {
                return null;
            }
            return new Route(
                isset($route['num_stops']) ? (int)$route['num_stops'] : null,
                isset($route['tickets']) ? (int)$route['tickets'] : null,
                $route['distance'] ?? null,
                $route['award_type'] ?? null,
                new Times($totalFlight, $totalLayover),
                new Redemptions(
                    $route['redemptions']['program'] ?? null,
                    isset($route['redemptions']['miles']) ? (float)$route['redemptions']['miles'] : null
                ),
                new Payments(
                    $currency,
                    $originalCurrency ?? null,
                    $conversionRate ?? null,
                    $taxes,
                    $fees
                ),
                $segments,
                $route['message'] ?? null,
                $route['classOfService'] ?? null
            );
        }, $result['routes'] ?? [], array_keys($result['routes'] ?? []));
        $currencies = array_unique($currencies);
        if (count($currencies) > 1) {
            $parseErrors[] = [
                "property" => "routes[*].payments.currency",
                "message" => "has more then one currency type: " . implode(', ', $currencies)
            ];
        }
        $routes = array_filter($routes);

        $response = clone $row->getResponse();
        $response->setRoutes($routes);

        // validation
        $responseSerialized = $this->serializer->serialize($response, 'json');
        $errors = $this->validator->validate(
            json_decode($responseSerialized, false),
            basename(str_replace('\\', '/', get_class($response))),
            $row->getApiVersion(),
            true
        );

        $this->logMessages($checker, $parseErrors, 'Parse errors:', true);

        $this->logMessages($checker, $parseNotices, 'Parse notices:', false);

        $this->logMessages($checker, $errors, 'Validation errors:', true);


        if (!empty($errors) || !empty($parseErrors)) {
            $checker->ErrorCode = ACCOUNT_ENGINE_ERROR;
            return;
        }

        if ($isCollected && empty($routes) && in_array($checker->ErrorCode, [ACCOUNT_WARNING, ACCOUNT_CHECKED])) {
            $checker->ErrorCode = ACCOUNT_ENGINE_ERROR;
            $checker->ErrorMessage = "Empty filtered result cannot have status equal to 1 or 9";
            return;
        }

        $response
            ->setState($checker->ErrorCode)
            ->setMessage($checker->ErrorCode === ACCOUNT_CHECKED ? '' : $checker->ErrorMessage)
        ;

        if ($this->partnerCanDebug($row->getPartner())) {
            $response->setDebugInfo($checker->DebugInfo);
        }

        $row->setResponse($response);
        $this->manager->persist($row);
        $this->manager->flush();
    }

    private function logMessages($checker, array $messages, string $msgStartWith, bool $logToKibana)
    {
        if (!empty($messages)) {
            $msg = $msgStartWith . ' ' . print_r(array_map(function (array $message) {
                    return ['property' => $message['property'], 'message' => $message['message']];
                }, $messages), true);
            if ($logToKibana) {
                $checker->logger->error($msg, ['pre' => true]);
                $this->logger->notice("Reward availability has errors", ['component' => 'parser']);
                return;
            }
            $checker->logger->notice($msg, ['pre' => true]);
        }
    }

    protected function prepareResponse(\TAccountChecker $checker, $request, &$response, $apiVersion, string $partner)
    {
        $response
            ->setState($checker->ErrorCode)
            ->setMessage($checker->ErrorCode === ACCOUNT_CHECKED ? '' : $checker->ErrorMessage)
            ->setUserData($request->getUserdata());

        $browserState = $checker->GetState();
        if (isset($browserState, $checker->AccountFields['AccountKey']) && in_array($checker->ErrorCode, [ACCOUNT_CHECKED, ACCOUNT_WARNING])) {
            $this->bsFactory->save($checker->AccountFields['AccountKey'], $browserState);
        }
        if (isset($checker->AccountFields['AccountKey']) && ($account = $this->manager->getRepository(RaAccount::class)->find($checker->AccountFields['AccountKey']))) {
            $account->setErrorCode($checker->ErrorCode);
            if (in_array($checker->ErrorCode, [ACCOUNT_CHECKED, ACCOUNT_WARNING])) {
                $account->setWarmedUp(RaAccount::WARMUP_DONE);
            }
            /*
            else {
                $account->setWarmedUp(RaAccount::WARMUP_NONE);
            }
            */
            $this->manager->persist($account);
            $this->manager->flush();
            $this->logger->info('ra account result', ['accountKey' => $account->getId(), 'errorCode' => $checker->ErrorCode, 'provider' => $account->getProvider(), 'warmedUp' => $account->getWarmedUp(), 'lockState' => $account->getLockState()]);

            if (in_array($checker->ErrorCode, [ACCOUNT_LOCKOUT, ACCOUNT_PREVENT_LOCKOUT])) {
                $this->logger->info($checker->ErrorCode . ': reset State of response for retry',
                    ['accountKey' => $checker->AccountFields['AccountKey']]);
                $response->setState(ACCOUNT_UNCHECKED);
                throw new \ThrottledException(0, $this->timeout, null, "account is lockout");
            }
        }
        if ($this->partnerCanDebug($partner)) {
            $response->setDebugInfo($checker->DebugInfo);
        }
    }

    public function unLockAccount($accountKey, $providerCode, $errorCode) {
        if (!$this->checkLockRAAccount($providerCode)) {
            return;
        }
        if ($accountKey && ($account = $this->manager->getRepository(RaAccount::class)->find($accountKey))) {
            $account->setErrorCode($errorCode);
            if ($errorCode !== ACCOUNT_UNCHECKED && $errorCode !== ACCOUNT_QUESTION) {
                $account->setLockState(RaAccount::PARSE_UNLOCK);
                $this->logger->info('unlock account for parse', ['accountKey' => $account->getId(), 'provider' => $account->getProvider()]);
            }
            $this->manager->flush();
        }
    }

    protected function saveResponseGeneral($response, &$row)
    {
        if ($row instanceof RewardAvailability
//            && in_array($response->getState(), [ACCOUNT_CHECKED, ACCOUNT_WARNING])
            && $response->getState() !== ACCOUNT_UNCHECKED
        ) {
            $this->sendToAW($row);
            // reset for juicymiles all ext.values (i.e. classOfService)
            /** @var Route $route */
            foreach($row->getResponse()->getRoutesToSerialize() as $route){
                $route->setIsFastest(null);
                $route->setIsCheapest(null);
                $route->setCabinPercentage(null);
                $route->setCabinType(null);
                $route->setClassOfService(null);
                /** @var Segment $segment */
                $segments = $route->getSegmentsToSerialize();
                foreach ($segments as $segment){
                    $segment->setClassOfService(null);
                }
            }
            $this->manager->persist($row);
            $this->manager->flush();
        }
        // debugInfo только для партнеров
        $canDebug = $this->partnerCanDebug($row->getPartner());
        if (!$canDebug) {
            $response->setDebuginfo(null);
        }

        $row->setUpdatedate(new DateTime());
        $this->manager->persist($row);
        $this->manager->flush();
    }

    /**
     * @param string $provider
     * @param bool $checkParseLock
     * @param mixed $accountKey
     * @param mixed $debugMode
     * @param mixed $exceptLockout
     * @return RaAccount
     */
    protected function pickAccount(string $provider, bool $checkParseLock, ?string $accountKey = null, ?bool $debugMode = false, ?bool $exceptLockout = false): ?RaAccount
    {
        $searched = false;
        if ('emiratesky' === $provider)
            $provider = 'skywards';
        /** @var RaAccount $account */
        if (isset($accountKey)) {
            $criteria = ['id' => $accountKey, 'provider' => $provider];
            $criteriaNot = $exceptLockout ? ['errorCode' => [ACCOUNT_LOCKOUT, ACCOUNT_PREVENT_LOCKOUT]] : [];
            $account = $this->manager->getRepository(RaAccount::class)->findOneByWithNot($criteria, $criteriaNot);
            $searched = true;
        } elseif ($debugMode) {
            $criteria = ['provider' => $provider, 'state' => RaAccount::STATE_DEBUG];
            $criteriaNot = $exceptLockout ? ['errorCode' => [ACCOUNT_LOCKOUT, ACCOUNT_PREVENT_LOCKOUT]] : [];
            $account = $this->manager->getRepository(RaAccount::class)->findOneByWithNot($criteria, $criteriaNot);
            $searched = true;
        }
        if ($searched) {
            if ($account && $account->getErrorCode() === ACCOUNT_PREVENT_LOCKOUT) {
                return $account;
            }
        } else {
            $priorityList = [];
            if (!$this->backgroundCheck) {
                $priorityList = $this->getListAccountsWithActiveHots($provider, $checkParseLock);
                $this->logger->info("priorityList: " . var_export($priorityList, true));
            }
            $account = $this->manager->getRepository(RaAccount::class)->findBestAccount($provider, $checkParseLock, $exceptLockout, $priorityList);
        }
        if ($account) {
            $this->markAccountIsInUse($account, $checkParseLock);
        }
        return $account;
    }

    private function markAccountIsInUse(RaAccount $account, ?bool $checkParseLock): void
    {
        $this->logger->info('pickAccount found', [
            'accountKey' => $account->getId(),
            'errorCode' => $account->getErrorCode(),
            'lockState' => $account->getLockState(),
            'accState' => $account->getState(),
            'warmUp' => $account->getWarmedUp()
        ]);
        $account->setLastUseDate(new DateTime());
        if ($account->getWarmedUp() === RaAccount::WARMUP_NONE && 'skywards' === $account->getProvider()) {
            $account->setWarmedUp(RaAccount::WARMUP_LOCK);
            $this->logger->info('locking account for warmup',
                ['accountKey' => $account->getId(), 'provider' => $account->getProvider()]);
        }
        if ($checkParseLock) {
            $account->setLockState(RaAccount::PARSE_LOCK);
            $this->logger->info('locking account for parse',
                ['accountKey' => $account->getId(), 'provider' => $account->getProvider()]);
        }
        $this->manager->flush();
        $this->logger->info('using account', [
            'accountKey' => $account->getId(),
            'provider' => $account->getProvider(),
            'warmedUp' => $account->getWarmedUp(),
            'lockState' => $account->getLockState()
        ]);
    }

    private function getListAccountsWithActiveHots(string $provider, bool $checkParseLock): array
    {
        $builder = $this->manager->createQueryBuilder(\AwardWallet\Common\Document\HotSession::class);
        if (!$builder) {
            return [];
        }
        $findQuery = $builder
            ->select('accountKey')
            ->field('provider')->equals($provider)
            ->field('isLocked')->equals(false)
            ->getQuery();
        $items = $findQuery->execute()->toArray();

        $items = array_map(function ($k) {
            /** @var HotSession $k */
            return $k->getAccountKey();
        }, $items);
        $listAccountsWithHot = array_filter(array_values($items));
        if (empty($listAccountsWithHot)) {
            return [];
        }
        if (!$checkParseLock) {
            return $listAccountsWithHot;
        }
        $builder = $this->manager->createQueryBuilder(RaAccount::class);
        $findQuery = $builder
            ->select('_id')
            ->field('lockState')->equals(RaAccount::PARSE_UNLOCK)
            ->field('_id')->in($listAccountsWithHot)
            ->getQuery();
        $items = $findQuery->execute()->toArray();

        return array_keys($items);
    }

    private function getRate($result, $currencyIn)
    {
        if (!isset($result['routes']) || count($result['routes']) === 0) {
            return 1;
        }
        $currencyOut = $result['routes'][0]['payments']['currency'];
        if ($currencyOut == $currencyIn) {
            return 1;
        }
        return $this->currencyConverter->getExchangeRate($currencyOut, $currencyIn);
    }

    protected function checkLockRAAccount(string $providerCode): bool
    {
        $sql = <<<SQL
            SELECT RewardAvailabilityLockAccount
            FROM Provider
            WHERE Code = :CODE
SQL;
        $row = $this->connection->executeQuery($sql, [':CODE' => $providerCode])->fetch();
        return $row && $row['RewardAvailabilityLockAccount'];
    }

    protected function supportPackageCallback()
    {
        return false;
    }

    public function sendCallback(LoyaltyRequestInterface $request, $row)
    {
        BaseExecutor::sendCallback($request, $row);
    }

    public function getMongoDocumentClass(): string
    {
        return RewardAvailability::class;
    }

    protected function getRequestClass(int $apiVersion): string
    {
        return RewardAvailabilityRequest::class;
    }

    protected function getResponseClass(int $apiVersion): string
    {
        return RewardAvailabilityResponse::class;
    }

    private function parseSegments(array $connections, int $numRoute, Extra $extra): array
    {
        // TODO?? here's solvers and validators
        $totalFlight = null;
        $totalLayover = null;
        $hasError = false;
        $hasErrorTime = false;
        $errorMsg = [];
        $noticeMsg = [];
        $segments = [];
        // надо пройтись в прямом порядке сначала и "выровнять даты"
        $connections = $this->correctDates($connections);
        // reverse for correct calc layovers
        $connections = array_reverse($connections);
        $cnt = count($connections);
        foreach ($connections as $i => $segment) {
            $realNum = $cnt - $i - 1;
            $depDate = strtotime($segment['departure']['date']);
            $arrDate = strtotime($segment['arrival']['date']);
            $airline = $segment['airline'] ?? null;
            $cabin = $segment['cabin'] ?? null;
            if (!$depDate) {
                $hasError = true;
                $errorMsg[] = [
                    "property" => "routes[{$numRoute}].connections[{$realNum}].departure.date",
                    "message" => "format error"
                ];
            }
            if (!$arrDate) {
                $hasError = true;
                $errorMsg[] = [
                    "property" => "routes[{$numRoute}].connections[{$realNum}].arrival.date",
                    "message" => "format error"
                ];
            }
            if (!preg_match("/^[A-Z]{3}$/", $segment['departure']['airport'])) {
                $hasError = true;
                $errorMsg[] = [
                    "property" => "routes[{$numRoute}].connections[{$realNum}].departure.airport",
                    "message" => "not /^[A-Z]{3}$/"
                ];
            }
            if (!preg_match("/^[A-Z]{3}$/", $segment['arrival']['airport'])) {
                $hasError = true;
                $errorMsg[] = [
                    "property" => "routes[{$numRoute}].connections[{$realNum}].arrival.airport",
                    "message" => "not /^[A-Z]{3}$/"
                ];
            }
            if (!preg_match("/^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])$/", $airline)) {
                $hasError = true;
                $errorMsg[] = [
                    "property" => "routes[{$numRoute}].connections[{$realNum}].airlineCode",
                    "message" => "not /^(?:[A-Z\d][A-Z]|[A-Z][A-Z\d])$/"
                ];
            }
            if (empty($cabin)) {
                $hasError = true;
                $errorMsg[] = [
                    "property" => "routes[{$numRoute}].connections[{$realNum}].cabin",
                    "message" => "empty"
                ];
            }

            $flightStr = null;
            $layoverStr = null;
            $departure = $this->airportTime->convertToGmt($depDate, $segment['departure']['airport']);
            $arrival = $this->airportTime->convertToGmt($arrDate, $segment['arrival']['airport']);
            if (isset($prevDeparture)) {
                $layover = $prevDeparture - $arrival;
                if ($layover < 0) {
                    $hasErrorTime = true;
                    $flightData = $segment['departure']['airport'] . '-' . $segment['arrival']['airport'] . ':' . $segment['flight'][0];
                    $noticeMsg[] = [
                        "property" => "routes[{$numRoute}].connections[{$realNum}]",
                        "message" => "departure earlier than the previous arrival (route skipped: {$flightData})"
                    ];
                } else {
                    $totalLayover += $layover;
                    $layoverStr = $this->formatTime($layover);
                }
            }
            $flight = $arrival - $departure;
            if ($flight < 0) {
                $hasErrorTime = true;
                $flightData = $segment['departure']['airport'] . '-' . $segment['arrival']['airport'] . ':' . $segment['flight'][0];
                $noticeMsg[] = [
                    "property" => "routes[{$numRoute}].connections[{$realNum}]",
                    "message" => "arrival earlier than the departure (route skipped: {$flightData})"
                ];
            } else {
                $totalFlight += $flight;
                $flightStr = $this->formatTime($flight);
            }

            $sData = null;
            $dataKey = $i . $segment['flight'][0];
            // not delete for now, if JM will change their minds
            /*if (empty($segment['aircraft'])) {
                $carrier = $segment['airline'] ?? null;
                $flight = preg_replace('/^([A-Z\d][A-Z]|[A-Z][A-Z\d])/', '', $segment['flight'][0] ?? null);
                $depDate = strtotime($segment['departure']['date']);
                $depCode = $segment['departure']['airport'] ?? null;
                $arrCode = $segment['arrival']['airport'] ?? null;

                if ($this->fsh->isFsEnabled($extra)) {
                    $context = $this->fsh->getContext(
                        $carrier, $flight,
                        $depCode, $arrCode,
                        $depDate, null, $extra->context->partnerLogin, null, null);
                    if ($context->getEligible()) {
                        $sData = $this->fsh->process(
                            $context,
                            $carrier, $flight,
                            $depCode, $arrCode,
                            $depDate, null,
                            null, null,
                            $extra, $dataKey);
                        if ($context->wasCallMade()) {
                            $extra->solverData->addFsCall($dataKey, $context->getMethod());
                        }
                        if (isset($sData)) {
                            $segment['aircraft'] = $sData->aircraftIata;;
                        }
                    }
                }
            }*/

            if (in_array($airline, ['UA', 'AS', 'DL']) && $cabin === 'firstClass') {
                $cabin = 'business';
                $noticeMsg[] = [
                    "property" => "routes[{$numRoute}].connections[{$realNum}].cabin",
                    "message" => "was changed firstClass->business"
                ];
            }
            $segments[] = new Segment(
                new SegmentPoint(
                    $segment['departure']['date'] ?? null,
                    $segment['departure']['airport'] ?? null,
                    $segment['departure']['terminal'] ?? null
                ),
                new SegmentPoint(
                    $segment['arrival']['date'] ?? null,
                    $segment['arrival']['airport'] ?? null,
                    $segment['departure']['terminal'] ?? null
                ),
                $segment['meal'] ?? null,
                $cabin,
                $segment['fare_class'] ?? null,
                isset($segment['tickets']) ? (int)$segment['tickets'] : null,
                $segment['flight'] ?? null,
                $airline,
                $segment['aircraft'] ?? null,
                new Times(
                    $hasError ? null : $flightStr,
                    $hasError ? null : $layoverStr
                ),
                $segment['num_stops'] ?? null,
                $segment['classOfService'] ?? null
            );
            $prevDeparture = $departure;
        }
        if ($hasError || $hasErrorTime) {
            // reset data
            $totalFlight = null;
            $totalLayover = null;
        } else {
            $totalFlight = $this->formatTime($totalFlight);
            $totalLayover = $this->formatTime($totalLayover);
        }
        $segments = array_reverse($segments);
        return [$segments, $totalFlight, $totalLayover, $errorMsg, $noticeMsg];
    }

    private function correctDates(array $connections): array
    {
        $corrector = new DateCorrector();
        $previous = null;
        foreach ($connections as $i => &$segment) {
            $depDate = strtotime($segment['departure']['date']);
            $arrDate = strtotime($segment['arrival']['date']);
            $depCode = $segment['departure']['airport'] ?? null;
            $arrCode = $segment['arrival']['airport'] ?? null;

            // simple condition - next dep is always later then previous arr
            if (!empty($previous) && $date = $corrector->fixDateNextSegment($previous, $depDate)) {
                $segment['departure']['date'] = date("Y-m-d H:i", $date);
                $depDate = strtotime($segment['departure']['date']);
            }
            // calculating tz offset between airports and adjusting dates
            if (($date = $corrector->fixDateOvernightSegment($depDate, $this->fh->getAirportOffset($depCode, true), $arrDate,
                $this->fh->getAirportOffset($arrCode, true)))) {
                $segment['arrival']['date'] = date("Y-m-d H:i", $date);
                $arrDate = strtotime($segment['arrival']['date']);
            }
            $previous = $arrDate;
        }
        return $connections;
    }

    private function formatTime(?int $seconds): ?string
    {
        if (!isset($seconds)) {
            return null;
        }
        $minutes = $seconds / 60;
        $h = (int)($minutes / 60);
        $m = $minutes % 60;
        return sprintf('%02d:%02d', $h, $m);
    }

    protected function checkInitCheckerBeforeParse(\TAccountChecker $checker): bool
    {
        $canCheck = true;
        try {
            $checker->checkInitBrowserRewardAvailability();
        } catch (\CheckException $e) {
            $checker->DebugInfo = $e->getMessage();
            $checker->ErrorCode = ACCOUNT_ENGINE_ERROR;
            $this->logger->error($e->getMessage());

            $browserState = $checker->GetState();
            if (isset($browserState, $checker->AccountFields['AccountKey'])
                && $this->bsFactory->clear($checker->AccountFields['AccountKey'])
            ) {
                $this->logger->notice('Browser State was cleared');
                $checker->ErrorCode = ACCOUNT_UNCHECKED;
                // try once again, maybe problem was with BrowserState
                throw new \CheckRetryNeededException(3, 0);
            }
            $canCheck = false;
        }
        return $canCheck;
    }

    private function sendToAW(RewardAvailability $row): void
    {
        try {
            $this->toAW->sendMessage($row);
        } catch (ExitException $e) {
            $this->logger->info('some problems with sendMessage to queue ' . SendToAW::QUEUE_NAME,
                ['error_message' => $e->getMessage(), 'error_trace' => $e->getTraceAsString()]);
        }
    }

    public function getMethodKey(): string
    {
        return RewardAvailability::METHOD_KEY;
    }

}