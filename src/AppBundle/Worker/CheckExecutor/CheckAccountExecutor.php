<?php

namespace AppBundle\Worker\CheckExecutor;

use AppBundle\Document\BaseDocument;
use AppBundle\Document\CheckAccount;
use AppBundle\Document\RegisterAccount;
use AppBundle\Event\CheckAccountFinishEvent;
use AppBundle\Event\CheckAccountStartEvent;
use AppBundle\Extension\CheckerFactory;
use AppBundle\Extension\HistoryProcessor;
use AppBundle\Extension\Loader;
use AppBundle\Extension\MQSender;
use AppBundle\Extension\ProvidersHelper;
use AppBundle\Extension\S3Custom;
use AppBundle\Extension\TimeCommunicator;
use AppBundle\Extension\Watchdog;
use AppBundle\Model\Resources\Answer;
use AppBundle\Model\Resources\BaseCheckRequest;
use AppBundle\Model\Resources\BrowserState;
use AppBundle\Model\Resources\ChangePasswordRequest;
use AppBundle\Model\Resources\CheckAccountRequest;
use AppBundle\Model\Resources\CheckAccountResponse;
use AppBundle\Model\Resources\DetectedCard;
use AppBundle\Model\Resources\ExtensionAnswersConverter;
use AppBundle\Model\Resources\History;
use AppBundle\Model\Resources\HistoryColumn;
use AppBundle\Model\Resources\Interfaces\LoyaltyRequestInterface;
use AppBundle\Model\Resources\Property;
use AppBundle\Model\Resources\RequestItemHistory;
use AppBundle\Model\Resources\SubAccount;
use AppBundle\Model\Resources\UserData;
use AppBundle\Model\Resources\V2\BaseCheckResponse;
use AppBundle\Service\ApiValidator;
use AwardWallet\Common\AWS\Util;
use AwardWallet\Common\CurrencyConverter\CurrencyConverter;
use AwardWallet\Common\Parsing\Exception\ErrorFormatter;
use AwardWallet\Common\Parsing\Filter\ItinerariesFilter;
use AwardWallet\Common\Parsing\Solver\MasterSolver;
use AwardWallet\ExtensionWorker\ClientFactory;
use AwardWallet\ExtensionWorker\Credentials;
use AwardWallet\ExtensionWorker\FileLogger;
use AwardWallet\ExtensionWorker\LoginWithIdInterface;
use AwardWallet\ExtensionWorker\ParseHistoryOptions;
use AwardWallet\ExtensionWorker\ParseInterface;
use AwardWallet\ExtensionWorker\ParseItinerariesOptions;
use AwardWallet\ExtensionWorker\ParseAllOptions;
use AwardWallet\ExtensionWorker\ParserFactory;
use AwardWallet\ExtensionWorker\ParserLogger;
use AwardWallet\ExtensionWorker\ParserRunner;
use AwardWallet\ExtensionWorker\ProviderInfo;
use AwardWallet\ExtensionWorker\ProviderInfoFactory;
use AwardWallet\ExtensionWorker\SelectParserRequest;
use AwardWallet\ExtensionWorker\SessionManager;
use AwardWallet\ExtensionWorker\WarningLogger;
use AwardWallet\Schema\Parser\Component\InvalidDataException;
use AwardWallet\Schema\Parser\Component\Master;
use AwardWallet\Schema\Parser\Component\Options;
use AwardWallet\Schema\Parser\Util\ArrayConverter;
use Doctrine\DBAL\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use JMS\Serializer\Exception\RuntimeException;
use JMS\Serializer\SerializerInterface;
use Monolog\Handler\PsrHandler;
use OldSound\RabbitMqBundle\RabbitMq\ProducerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CheckAccountExecutor extends BaseExecutor implements ExecutorInterface
{

    const STATE_PREFIX_V1 = 'v1:';
    const STATE_PREFIX_V2 = 'v2:';
    const STATE_PREFIX_ACTUAL = self::STATE_PREFIX_V2;
    const STATE_PREFIX_BASE64 = 'base64:';

    protected $RepoKey = CheckAccount::METHOD_KEY;

    /** @var HistoryProcessor */
    private $historyProcessor;
    private ParserFactory $parserFactory;
    private ParserRunner $parserRunner;
    private ClientFactory $clientFactory;
    private ProviderInfoFactory $providerInfoFactory;

    public function __construct(
        LoggerInterface          $logger,
        Connection               $connection,
        Connection               $sharedConnection,
        DocumentManager          $manager,
        Loader                   $loader,
        CheckerFactory           $factory,
        S3Custom                 $s3Client,
        ProducerInterface        $callbackProducer,
        MQSender                 $mqSender,
        SerializerInterface      $serializer,
        ProducerInterface $delayedProducer,
        \Memcached $memcached,
        $aesKey,
        ApiValidator             $validator,
        ItinerariesFilter        $itinerariesFilter,
        MasterSolver             $solver,
        Watchdog                 $watchdog,
        EventDispatcherInterface $eventDispatcher,
        Util $awsUtil,
        CurrencyConverter $currencyConverter,
        TimeCommunicator $timeCommunicator,
        ParserFactory $parserFactory,
        ParserRunner $parserRunner,
        ClientFactory $clientFactory,
        ProviderInfoFactory $providerInfoFactory
    )
    {
        parent::__construct($logger, $connection, $sharedConnection, $manager, $loader, $factory, $s3Client, $callbackProducer, $mqSender,
            $serializer, $delayedProducer, $memcached, $aesKey, $validator, $itinerariesFilter, $solver, $watchdog,
            $eventDispatcher, $awsUtil, $currencyConverter, $timeCommunicator);

        $this->parserFactory = $parserFactory;
        $this->parserRunner = $parserRunner;
        $this->clientFactory = $clientFactory;
        $this->providerInfoFactory = $providerInfoFactory;
    }

    /**
     * @param HistoryProcessor $historyProcessor
     */
    public function setHistoryProcessor($historyProcessor)
    {
        $this->historyProcessor = $historyProcessor;
    }

    /**
     * @param CheckAccountRequest $request
     * @param BaseDocument $row
     * @return \TAccountChecker
     * @throws \AppBundle\Extension\CheckerFactoryException
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function buildChecker($request, BaseDocument $row): \TAccountChecker {
        $checker = parent::buildChecker($request, $row);

        $source = 1; // default: UpdaterEngineInterface::SOURCE_DESKTOP;
        if (!empty($request->getUserData()) && str_contains($request->getUserData(), 'source')) {
            /** @var UserData $userData */
            $userData = $this->serializer->deserialize($request->getUserData(), UserData::class, 'json');
            $source = $userData->getSource() ?? $source;
        }
        $checker->setSource($source);

        if (!empty($request->getAnswers())) {
            $answers = [];
            /** @var Answer $item */
            foreach ($request->getAnswers() as $item)
                $answers[$item->getQuestion()] = $item->getAnswer();

            $checker->Answers = $answers;
        }

        // резервации
        $checker->setParseIts(
            $checker->ParseIts && $request->getParseitineraries() === true
        );
        // резервации из прошлого
        $checker->ParsePastIts = $request->getParsePastIineraries();

        // логика историй
        /** @var RequestItemHistory $history */
        $history = $request->getHistory();
        if (!empty($history) && $history instanceof RequestItemHistory) {
            CheckerHistoryOptionsConverter::setCheckerHistoryOptions($this->historyProcessor->getParseHistoryOptions($history, $request->getProvider()), $checker);
        }

        /** @var UserData $userData */
        if (!empty($request->getUserData()) && str_contains($request->getUserData(), 'otcWait')) {
            try {
                $userData = $this->serializer->deserialize($request->getUserData(), UserData::class, 'json');
                if ($userData->getOtcWait() && method_exists($checker, 'setWaitForOtc')) {
                    $checker->setWaitForOtc(true);
                }
            } catch(RuntimeException $e){}
        }

        // TODO: логика файлов

        return $checker;
    }

    /**
     * @param \TAccountChecker $checker
     * @param CheckAccountRequest $request
     * @param BaseDocument $row
     * @throws \Exception
     */
    protected function processChecker(\TAccountChecker $checker, $request, BaseDocument $row, $fresh = true)
    {
        try {
            if ($fresh) {
                $checker->InitBrowser();
                $this->logServerInfo($checker->logger);
            }
            if ($row instanceof RegisterAccount) {
                $checker->http->RetryCount = 0;
            }
            $canCheck = true;
            if (method_exists($this, 'checkInitCheckerBeforeParse')){
                $canCheck = $this->checkInitCheckerBeforeParse($checker);
            }
            if ($canCheck) {
                try {
                    $checker->Check(false);
                } catch (\ThrottledException $e) {
                    if ($checker->throttlerReason === $checker::THROTTLER_REASON_START_RPM) {
                        $this->uploadOnS3 = false;
                        $checker->throttlerReason = null;
                    }
                    throw $e;
                }
            }
        } catch (\CheckAccountExceptionInterface $e) {
            $catched = false;
            if ($e instanceof \CheckRetryNeededException) {
                $this->processRetryException($e, $checker, $row);
                $catched = true;
            }

            if ($e instanceof \CancelCheckException) {
                $checker->logger->notice("CancelCheckException is thrown");
                $checker->ErrorMessage = 'Cancelled';
                $checker->ErrorCode = ACCOUNT_UNCHECKED;
                $catched = true;
            }

            if (!$catched)
                throw $e;
        }

        if (!empty($checker->Properties)) {
            if (!$this->dryValidateProperties($checker, $request->getProvider())) {
                $checker->Balance = null;
                $checker->Properties = [];
            }
        }

        if ($row instanceof RegisterAccount)
            return;

        $this->filter($checker, $request->getProvider());
    }

    /**
     * @param \TAccountChecker $checker
     * @param CheckAccountRequest $request
     * @param CheckAccountResponse $response
     * @param integer $apiVersion
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function prepareResponse(\TAccountChecker $checker, $request, &$response, $apiVersion, string $partner)
    {
        $browserState = $checker->GetState();

        $canCheckIt = $this->canCheckItineraries($request->getProvider());

        if ($request->getParseitineraries() && !$canCheckIt) {
            $response->addWarning(ProvidersHelper::DO_NOT_SUPPORT_ITINERARIES_WARNING);
        }

        $response->setState($checker->ErrorCode)
                 ->setMessage($checker->ErrorCode == ACCOUNT_CHECKED ? '' : $checker->ErrorMessage)
                 ->addDebuginfo($checker->DebugInfo)
                 ->setUserdata($request->getUserdata());

        // Invalid answers
        if (!empty($checker->InvalidAnswers)) {
            $invalidAnswers = [];
            foreach ($checker->InvalidAnswers as $questionItem => $answerItem)
                $invalidAnswers[] = (new Answer())->setQuestion($questionItem)->setAnswer($answerItem);

            $response->setInvalidanswers($invalidAnswers);
        }

        // History
        try {
            $history = $this->historyProcessor->prepareHistoryResults($checker);
            if ($history instanceof History) {
                $response->setHistory($history);
            }
        } catch (\CheckException $e) {
            $checker->ErrorCode = $e->getCode();
            $checker->ErrorMessage = $e->getMessage();
            $response
                ->setState($checker->ErrorCode)
                ->addDebuginfo($checker->DebugInfo)
                ->setMessage($checker->ErrorMessage);
        }

        if (in_array($checker->ErrorCode, [ACCOUNT_ENGINE_ERROR, ACCOUNT_TIMEOUT])) {
            return;
        }

        $response->setBalance($checker->Balance)
                 ->setLogin($checker->AccountFields['Login'])
                 ->setLogin2($checker->AccountFields['Login2'])
                 ->setLogin3($checker->AccountFields['Login3'])
                 ->setUserdata($request->getUserdata())
                 ->setCheckdate(new \DateTime())
                 ->setQuestion($checker->Question)
                 ->setBrowserstate(isset($browserState) ? $this->encodeBrowserState($browserState, $request) : null)
                 ->setErrorreason($checker->ErrorReason);

        // Expiration Date AND Never Expires
        if (isset($checker->Properties["AccountExpirationDate"])) {
            switch ($checker->Properties["AccountExpirationDate"]) {
                case false:
                    $response->setNeverexpires(true);
                    break;
                default:
                    $expDate = (new \DateTime())->setTimestamp($checker->Properties['AccountExpirationDate']);
                    $response->setExpirationDate($expDate);
                    break;
            }
            unset($checker->Properties["AccountExpirationDate"]);
        }

        // DetectedCards
        if (isset($checker->Properties["DetectedCards"]) && !empty($checker->Properties["DetectedCards"])) {
            $detectedCards = [];
            foreach ($checker->Properties["DetectedCards"] as $row) {
                $card = new DetectedCard();
                $card->setCode($row['Code'])
                     ->setDisplayname($row['DisplayName'])
                     ->setCarddescription($row['CardDescription']);

                $detectedCards[] = $card;
            }

            $response->setDetectedcards($detectedCards);
            unset($checker->Properties["DetectedCards"]);
        }

        // Properties
        $providerProperties = $this->getProviderProperties($request->getProvider(), $checker->AccountFields['Partner']);
        if (!empty($checker->Properties)) {
            $response->setProperties($this->handleProperties($checker->Properties, $providerProperties, [], $checker, $partner));
        }

        // Subaccounts
        if (!empty($checker->Properties["SubAccounts"])) {
            $subAccounts = [];
            foreach ($checker->Properties["SubAccounts"] as $subAccountRow) {
                $subAccount = new SubAccount();
                $subAccount->setCode(isset($subAccountRow['Code']) ? $subAccountRow['Code'] : null)
                           ->setDisplayname(isset($subAccountRow['DisplayName']) ? $subAccountRow['DisplayName'] : null)
//                           ->setPictures()
//                           ->setLocations()
                           ->setBalance(isset($subAccountRow['Balance']) ? $subAccountRow['Balance'] : null);

                if (isset($subAccountRow["ExpirationDate"])) {
                    switch ($subAccountRow["ExpirationDate"]) {
                        case false:
                            $subAccount->setNeverexpires(true);
                            break;
                        default:
                            $expDate = (new \DateTime())->setTimestamp($subAccountRow["ExpirationDate"]);
                            $subAccount->setExpirationDate($expDate);
                            break;
                    }
                    unset($subAccountRow["ExpirationDate"]);
                }


                $subAccountProperties = $this->handleProperties($subAccountRow, $providerProperties, ['Code', 'DisplayName', 'Balance'], $checker, $partner);
                $subAccount->setProperties($subAccountProperties);
                $subAccounts[] = $subAccount;
            }
            $response->setSubaccounts($subAccounts);
        }

        //Itineraries
        if ($request->getParseitineraries() && $canCheckIt) {
            $itineraries = $this->handleAccountCheckerItineraries($checker, $apiVersion, $partner);
            $response->setItineraries($itineraries);
            $response->setNoitineraries($checker->itinerariesMaster->getNoItineraries() === true);
        }

    }

    /**
     * @param CheckAccountResponse $response
     * @param CheckAccount $row
     */
    protected function saveResponse($response, &$row)
    {
        $row->setResponse(json_decode($this->serializer->serialize($response, 'json'), true))
            ->setUpdatedate(new \DateTime());
        $this->manager->persist($row);
        $this->manager->flush();
    }

    /**
     * @param CheckAccountRequest $request
     * @param string $partner
     * @return array
     */
    protected function prepareAccountInfo(BaseCheckRequest $request, string $partner, BaseDocument $doc) : array
    {
        $accountId = $request->getUserData();
        if($partner === 'awardwallet'){
            /** @var UserData $userData */
            if ($request->getUserData() !== null) {
                $userData = $this->serializer->deserialize($request->getUserData(), UserData::class, 'json');
                $accountId = $userData->getAccountId();
            }
        }

        $result = array_merge(
            parent::prepareAccountInfo($request, $partner, $doc),
            [
                'AccountID' => $accountId,
                'Login' => $request->getLogin(),
                'Login2' => $request->getLogin2(),
                'Login3' => $request->getLogin3(),
                'Pass' => !empty($request->getPassword()) ? DecryptPassword($request->getPassword()) : null,
            ]
        );

        if(!empty($request->getBrowserstate())) {
            $state = $this->decodeBrowserState($request);
            if (!empty($state)) {
                $result['BrowserState'] = $state;
            }
        }

        return $result;
    }

    /**
     * @param $provider (Provider Code)
     * @return array (Provider Properties)
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function getProviderProperties($provider, $partner)
    {
        $showHidden = $this->showHiddenProperties($partner);
        $providerId = $this->getProviderIdByCode($provider);

        $cacheKey = "prov_props_" . $provider . "_" . $showHidden;
        $result = $this->memcached->get($cacheKey);

        if ($result === false) {
            $sqlProperties = <<<SQL
            SELECT pp.ProviderPropertyID, pp.Name, pp.Code, pp.Kind 
            FROM ProviderProperty pp 
            WHERE (pp.ProviderID = :PROVIDER_ID OR pp.ProviderID is null)
            AND pp.Visible > :VISIBLE
            ::INVISIBLE_TO_PARTNERS::
            ORDER BY pp.SortIndex
SQL;

            if ($partner === "awardwallet") {
                $replacement = "";
            } else {
                $replacement = "AND pp.Visible <> 3";
            }
            $sqlProperties = str_replace("::INVISIBLE_TO_PARTNERS::", $replacement, $sqlProperties);

            $visible = $showHidden === 1 ? -1 : 0;
            $providerProperties = $this->connection->executeQuery(
                $sqlProperties,
                [':PROVIDER_ID' => $providerId, ':VISIBLE' => $visible]
            );

            $result = [];
            foreach ($providerProperties as $property) {
                $result[$property['Code']] = $property;
            }
        }

        return $result;
    }

    private function showHiddenProperties(string $partner) : int
    {
        $cacheKey = "partner_hp_" . $partner;
        $result = $this->memcached->get($cacheKey);
        if($result === false) {
            $sql = <<<SQL
                SELECT ReturnHiddenProperties
                FROM Partner
                WHERE Login = :PARTNER
SQL;
            $result = $this->connection->executeQuery($sql, [':PARTNER' => $partner])->fetchColumn();
            $this->memcached->set($cacheKey, $result, 180);
        }
        return $result;
    }

    private function getProviderIdByCode(string $code) : int
    {
        $cacheKey = "provider_id_by_code_" . $code;
        $result = $this->memcached->get($cacheKey);
        if($result === false) {
            $result = $this->connection->executeQuery(
                "select ProviderID from Provider where Code = :CODE",
                [':CODE' => $code]
            )->fetchColumn();
            $this->memcached->set($cacheKey, $result, 180);
        }
        return $result;
    }

    protected function handleProperties(array $checkerProperties, array $providerProperties, array $ignoreProps = [], \TAccountChecker $checker, string $partner)
    {
        $ignoreProps = array_merge($ignoreProps, ['ExpirationDateCombined']);
        $result = [];
        foreach ($checkerProperties as $propCode => $propValue) {
            if(is_array($propValue) || in_array($propCode, $ignoreProps))
                continue;

            if (!isset($providerProperties[$propCode])) {
                if($partner === 'awardwallet') { // why awardwallet only ?
                    $checker->sendNotification("Undefined provider property \"$propCode\". Important to define it in ProviderProperty table");
                }
                continue;
            }

            $property = $providerProperties[$propCode];
            $propItem = new Property();
            $propItem->setCode($property['Code'])
                     ->setKind($property['Kind'])
                     ->setName($property['Name'])
                     ->setValue(iconv("UTF-8", "UTF-8//IGNORE", $propValue));
            $result[] = $propItem;
        }

        return $result;
    }

    private function dryValidateProperties(\TAccountChecker $checker, $providerCode)
    {
        $m = new Master('m', new Options());
        $m->getLogger()->pushHandler(new PsrHandler($checker->logger));
        try {
            ArrayConverter::convertMaster(['Properties' => $checker->Properties], $m);
            $m->getStatement()->loadProviderProperties($providerCode, $this->loadPropertyKinds($providerCode));
            $m->getStatement()->validateProperties();
        }
        catch(InvalidDataException $e) {
            $checker->sendNotification($e->getMessage());
            $this->logger->notice($e->getMessage());
            return false;
        } finally {
            $m->getLogger()->popHandler();
        }

        $checkerHistoryColumns = $checker->GetHistoryColumns();
        if (is_array($checkerHistoryColumns)) {
            $historyColumns = [];
            foreach ($checkerHistoryColumns as $name => $kind) {
                $historyColumns[$name] = HistoryColumn::createFromTAccountCheckerDefinition($name, $kind);
            }
            if (!empty($checker->History) && !empty($messages = $this->dryValidateHistory($checker->History, $historyColumns))) {
                $checker->History = [];
                foreach ($messages as $message) {
                    $checker->sendNotification($message . ' (in main history)');
                    $this->logger->notice($message);
                }
            }
            if (!empty($checker->Properties['SubAccounts'])) {
                foreach ($checker->Properties['SubAccounts'] as $i => $sub) {
                    if (!empty($sub['HistoryRows']) && !empty($messages = $this->dryValidateHistory($sub['HistoryRows'], $historyColumns))) {
                        unset($checker->Properties['SubAccounts'][$i]['HistoryRows']);
                        foreach ($messages as $message) {
                            $checker->sendNotification($message . sprintf(' (in subacc %d history)', $i));
                            $this->logger->notice($message);
                        }
                    }
                }
            }
        }

        return true;
    }

    private function dryValidateHistory($history, $historyColumns): array
    {
        $errors = [];
        foreach ($history as $row) {
            $miles = $amount = null;
            foreach ($row as $keyField => $valueField) {
                if (!isset($historyColumns[$keyField])){
                    continue;
                }
                $columnCode = $keyField === 'Position' ? 'Position' : $historyColumns[$keyField]->getKind();
                switch ($columnCode) {
                    case HistoryColumn::BONUS_COLUMN:
                        $bonus = filterBalance($valueField, true);
                        if (null !== $bonus && ($bonus < -100000000 || $bonus > 100000000)) {
                            $errors[] = "wrong data in {$keyField} at history";
                        }
                        break;
                    case HistoryColumn::MILES_COLUMN:
                        $miles = filterBalance($valueField, true);
                        if (null !== $miles && ($miles < -100000000 || $miles > 100000000)) {
                            $errors[] = "wrong data in {$keyField} at history";
                        }
                        break;
                    case HistoryColumn::AMOUNT_COLUMN:
                        $amount = filterBalance($valueField, true);
                        break;
                }
            }
            if (isset($miles) && isset($amount) && (($miles > 0 && $amount < 0) || ($miles < 0 && $amount > 0))) {
                $errors[] = 'Amount/Miles values are invalid';
            }
        }
        return array_unique($errors);
    }

    /**
     * @param \TAccountChecker $checker
     * @param $provider (Provider Code)
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function filter(\TAccountChecker &$checker, $provider)
    {
        $sql = <<<SQL
            SELECT p.AllowFloat
            FROM Provider p
            WHERE p.Code = :PROVIDER
            ORDER BY p.Code
SQL;
        $options = $this->connection->executeQuery($sql, [':PROVIDER' => $provider])->fetch();

//---------Migrate OLD filterSubAccounts(array &$properties, &$balance, $fields)---------------------------------
        if (!empty($checker->Properties["SubAccounts"])) {
            // discard subaccounts with same non-zero, not lower than 50, divided to 10 with reminder, balance
//            for($key = 0; $key < count($checker->Properties['SubAccounts']); $key++){
            foreach ($checker->Properties['SubAccounts'] as $key => &$subAccount) {
//                $subAccount = $checker->Properties['SubAccounts'][$key];
                if( !array_key_exists('Balance', $subAccount)
                    ||
                    (isset($subAccount['Balance']) && trim($subAccount['Balance']) === '')
                ){
                    unset($checker->Properties['SubAccounts'][$key]);
                    continue;
                }

                if(isset($subAccount['Balance']))
                    $subAccount['Balance'] = filterBalance($subAccount['Balance'], $options['AllowFloat'] == '1');
                if (isset($subAccount['Balance']) && ($subAccount['Balance'] >= 50) && (($subAccount['Balance'] % 10) > 0)) {
                    foreach ($checker->Properties['SubAccounts'] as $matchKey => &$matchedSubAccount) {
                        if (($key != $matchKey)
                            && isset($matchedSubAccount['Balance'])
                            && ($matchedSubAccount['Balance'] == $subAccount['Balance'])
                            && ($matchedSubAccount['DisplayName'] == $subAccount['DisplayName'])
                            && (!isset($subAccount['Kind']) || ($subAccount['Kind'] != 'C'))
                        )
                            unset($checker->Properties['SubAccounts'][$matchKey]);
                    }
                }
            }

            // convert single subaccount to main account
            if (
                (!isset($checker->Properties['CombineSubAccounts']) || $checker->Properties['CombineSubAccounts'])
                &&
                ($checker->Balance === null && count($checker->Properties['SubAccounts']) === 1)
            ) {
                $values = array_values($checker->Properties['SubAccounts']);
                $subAccount = array_pop($values); // do not assume that subaccounts have numeric keys
                if (!isset($subAccount['Kind']) || ($subAccount['Kind'] != 'C')) // skip coupons
                    if (isset($subAccount['Balance'])) {
                        // all conditions matched, discard subaccount, copy to main
                        $checker->Balance = $subAccount['Balance'];
                        if (isset($subAccount['ExpirationDate'])) {
                            $checker->Properties['AccountExpirationDate'] = $subAccount['ExpirationDate'];
                            $checker->Properties['ExpirationDateCombined'] = true;
                        }
                        foreach ($subAccount as $key => $value)
                            if (!in_array($key, array("Code", "DisplayName", "Balance", "ExpirationDate", "Kind")))
                                $checker->Properties[$key] = $value;
                        unset($checker->Properties['SubAccounts']);
                    }
            }
        }
        unset($checker->Properties['CombineSubAccounts']);
//----------------------------------------------
        $checker->Balance = filterBalance($checker->Balance, $options['AllowFloat'] == '1');
        $checker->ErrorCode = intval($checker->ErrorCode);
        # Filter properties
        FilterAccountProperties($checker->Properties, null, true, $checker->getAllowHtmlProperties());
        #Filter trips
//        $this->FilterTrips();

        $checker->ErrorMessage = CleanXMLValue($checker->ErrorMessage);
        $checker->ErrorReason = CleanXMLValue($checker->ErrorReason);
        $checker->DebugInfo = CleanXMLValue($checker->DebugInfo);

        if (($checker->ErrorCode == ACCOUNT_INVALID_PASSWORD) && ($checker->ErrorMessage == "")) {
            $checker->ErrorMessage = 'Invalid username or password';
        }

        if (($checker->ErrorCode == ACCOUNT_QUESTION) && $checker->ErrorMessage == parent::UNKNOWN_ERROR) {
            $checker->ErrorMessage = $checker->Question;
        }

        unset($checker->Properties['ParseIts']);

        if ($checker->ErrorCode == ACCOUNT_CHECKED)
            $checker->ErrorMessage = '';

        if ($checker->Balance == 0 && isset($checker->Properties['AccountExpirationDate']) && ($checker->Properties['AccountExpirationDate'] !== false))
            unset($checker->Properties['AccountExpirationDate']);
    }

    /**
     * @param CheckAccountRequest|ChangePasswordRequest $request
     * @return string
     */
    protected function decodeBrowserState($request) : string
    {
        $state = $request->getBrowserstate();

        if (strpos($state, self::STATE_PREFIX_BASE64) === 0) {
            $result = base64_decode(substr($state, strlen(self::STATE_PREFIX_BASE64)));
            if ($result === false) {
                return "";
            }
            return $result;
        }

        if (strpos($state, self::STATE_PREFIX_V1) === 0)
            return AESDecode(base64_decode(substr($state, strlen(self::STATE_PREFIX_V1))), $this->aesKey);

        // BrowserState object
        if (strpos($state, self::STATE_PREFIX_V2) === 0)
        {
            $resultStr = AESDecode(base64_decode(substr($state, strlen(self::STATE_PREFIX_V2))), $this->aesKey);
            if (empty($resultStr)) {
                return "";
            }
            /** @var BrowserState $browserState */
            $browserState = $this->serializer->deserialize($resultStr, BrowserState::class, 'json');
            $loginHash = sha1($request->getLogin().$request->getLogin2().$request->getLogin3());
            if($loginHash === $browserState->getLoginHash())
                return $browserState->getBrowser();
        }

        return "";
    }

    /**
     * @param $state
     * @param CheckAccountRequest|ChangePasswordRequest $request
     * @return string
     */
    protected function encodeBrowserState($state, $request)
    {
        $browserState = (new BrowserState())
                        ->setBrowser($state)
                        ->setLoginHash(sha1($request->getLogin().$request->getLogin2().$request->getLogin3()));

        $state = $this->serializer->serialize($browserState, 'json');
        return self::STATE_PREFIX_ACTUAL.base64_encode(AESEncode($state, $this->aesKey));
    }


    /**
     * @param CheckAccountRequest $request
     * @param CheckAccountResponse $response
     * @param CheckAccount $row
     * @throws \Doctrine\DBAL\DBALException
     */
    public function processRequest($request, $response, $row)
    {
        $this->eventDispatcher->dispatch(CheckAccountStartEvent::NAME, new CheckAccountStartEvent($row));
        parent::processRequest($request, $response, $row);
    }

    /**
     * @param BaseCheckRequest $request
     * @param CheckAccount $row
     * @throws \Doctrine\DBAL\DBALException
     */
    public function sendCallback(LoyaltyRequestInterface $request, $row)
    {
        $this->eventDispatcher->dispatch(CheckAccountFinishEvent::NAME, new CheckAccountFinishEvent($row));
        parent::sendCallback($request, $row);
    }

    protected function supportPackageCallback()
    {
        return true;
    }

    public function getMongoDocumentClass(): string
    {
        return CheckAccount::class;
    }

    protected function getRequestClass(int $apiVersion): string
    {
        return CheckAccountRequest::class;
    }

    protected function getResponseClass(int $apiVersion): string
    {
        if (2 === $apiVersion) {
            return \AppBundle\Model\Resources\V2\CheckAccountResponse::class;
        }

        return CheckAccountResponse::class;
    }

    public function getMethodKey(): string
    {
        return CheckAccount::METHOD_KEY;
    }

    /**
     * @param \AppBundle\Model\Resources\V2\CheckAccountResponse $response
     * @param CheckAccountRequest $request
     */
    protected function runBrowserExtension(BaseCheckRequest $request, \AppBundle\Model\Resources\BaseCheckResponse $response, ParserLogger $parserLogger, array $state, int $apiVersion, string $partner)
    {
        $response->setCheckdate(new \DateTime());
        $this->logServerInfo($this->logger);
        $selectParserRequest = new SelectParserRequest(
            $request->getLogin2(),
            $request->getLogin3()
        );
        $providerInfo = $this->providerInfoFactory->createProviderInfo($request->getProvider());
        /** @var LoginWithIdInterface $parser */
        $parser = $this->parserFactory->getParser($request->getProvider(), $parserLogger, $selectParserRequest, $providerInfo);
        if ($parser === null) {
            $response->setErrorreason('Parser not found');

            return;
        }

        $errorFormatter = new ErrorFormatter($providerInfo->getDisplayName(), $providerInfo->getShortName());
        $client = $this->clientFactory->createClient($request->getBrowserExtensionSessionId(), $parserLogger->getFileLogger(), $errorFormatter);
        $credentials = new Credentials($request->getLogin(), $request->getLogin2(), $request->getLogin3(), DecryptPassword($request->getPassword()), ExtensionAnswersConverter::convert($request->getAnswers() ?? []));

        try {
            $result = $this->parserRunner->loginWithLoginId($parser, $client, $credentials, null, $request->getLoginId() ?? '', true, $parserLogger->getFileLogger());

            if ($result->loginResult->success) {
                $options = new Options();
                $options->throwOnInvalid = true;
                $options->logDebug = true;

                $master = new Master('main', $options);
                $master->addPsrLogger($this->logger);

                $parseHistoryOptions = null;
                if (!empty($request->getHistory()) && $request->getHistory() instanceof RequestItemHistory) {
                    $parseHistoryOptions = $this->historyProcessor->getParseHistoryOptions($request->getHistory(), $request->getProvider());
                }

                $parseItinerariesOptions = null;
                if ($request->getParseitineraries()) {
                    $parseItinerariesOptions = new ParseItinerariesOptions($request->getParsePastIineraries() ?? false);
                }

                $parseOptions = new ParseAllOptions(
                    $credentials,
                    $parseItinerariesOptions,
                    $parseHistoryOptions
                );
                /** @var ParseInterface $parser */
                $this->parserRunner->parseAll($parser, $result->tab, $master, $parseOptions, false, $parserLogger->getFileLogger());
                $this->saveResponseFromMaster($master, $request, $response, $apiVersion, $partner);
            } else {
                $result->tab->close();
                if ($result->loginResult->errorCode && $result->loginResult->errorCode !== ACCOUNT_ENGINE_ERROR) {
                    $response->setState($result->loginResult->errorCode);
                    $response->setMessage($errorFormatter->format($result->loginResult->error));
                }
            }
        }
        catch (\CheckException $checkException) {
            $actualException = $checkException;
            if ($checkException->getPrevious() === null) {
                $this->logger->notice("CheckException: " . $checkException->getMessage() . " at " . $checkException->getFile() . ':' . $checkException->getLine());
            } else {
                $actualException = $checkException->getPrevious();
            }

            $response->setState($checkException->getCode());
            if ($checkException->getCode() === ACCOUNT_ENGINE_ERROR) {
                global $arAccountErrorCode;
                $response->setMessage($arAccountErrorCode[ACCOUNT_ENGINE_ERROR]);
                $response->setDebuginfo($actualException->getMessage());
            } else {
                $response->setMessage($errorFormatter->format($actualException->getMessage()));
            }
        }
   }

    /**
     * @param \AppBundle\Model\Resources\V2\CheckAccountResponse $response
     */
    private function saveResponseFromMaster(Master $master, CheckAccountRequest $request, \AppBundle\Model\Resources\BaseCheckResponse $response, int $apiVersion, string $partner) : void
    {
        try {
            $master->checkValid();
        }
        catch (InvalidDataException $e) {
            $this->logger->error("validation failed: " . $e->getMessage() . ", will clear itineraries");
            $master->clearItineraries();
        }

        try {
            $master->checkValid();

            if ($master->getStatement()) {
                $this->handleMasterStatement($master, $request, $response, $partner);
            }

            if ($response->getState() === ACCOUNT_CHECKED && $request->getParseitineraries() && $this->canCheckItineraries($request->getProvider())) {
                $itineraries = $this->handleMasterItineraries($master, $request->getProvider(), $apiVersion, $partner);
                $response->setItineraries($itineraries);
                $response->setNoitineraries($master->getNoItineraries() === true);
            }
        }
        catch(InvalidDataException $e) {
            $this->logger->error("validation failed: " . $e->getMessage());

            return;
        }

    }

    private function loadPropertyKinds($providerCode) : array
    {
        return $this->connection->executeQuery(
            'select Code, Kind from ProviderProperty where ProviderID in (select ProviderID from Provider where Code = ?) or ProviderID is null',
            [$providerCode])
            ->fetchAllKeyValue();
    }

    private function canCheckItineraries(string $provider) : bool
    {
        return
            $this->connection->executeQuery(
                "SELECT 1 FROM Provider p WHERE p.Code = :PROVIDER AND p.CanCheckItinerary = 1",
                [':PROVIDER' => $provider]
            )->fetchOne() !== false;
    }

    private function handleMasterStatement(Master $master, CheckAccountRequest $request, \AppBundle\Model\Resources\V2\CheckAccountResponse $response, string $partner)
    {
        $master->getStatement()->loadProviderProperties($request->getProvider(), $this->loadPropertyKinds($request->getProvider()));
        $master->getStatement()->validateProperties(); // included in checkValid() above ?
        $extra = $this->getExtra($partner, $request->getProvider());
        $loyaltyInfo = (new \AwardWallet\Common\API\Converter\V2\Statement())->convert($master->getStatement(), $extra);

        // how to handle balance = "n/a" ? use noBalance property?
        if ($loyaltyInfo && $loyaltyInfo->balance !== null) {
            $response->setState(ACCOUNT_CHECKED);
            $response->setBalance($loyaltyInfo->balance);
            $response->setProperties($loyaltyInfo->properties);
        }
    }

}